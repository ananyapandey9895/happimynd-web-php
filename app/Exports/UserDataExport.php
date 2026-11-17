<?php

namespace App\Exports;

use App\Models\User;
use App\Services\UserProductService;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * User data download (Excel / CSV) for the View Users pages.
 *
 *  - product = null       : all users, date range on registration date
 *  - product = 'none'     : users who have not started any product
 *  - product = happilife… : users of that product, date range on the date they started it
 *
 * Uses FromQuery so rows are read in chunks with relations eager loaded
 * (the old export loaded every user and ran several queries per row).
 */
class UserDataExport implements FromQuery, WithHeadings, WithMapping
{
    protected $product;
    protected $from;
    protected $to;
    protected $hasViewCount = false;
    protected $rowNumber = 0;

    public function __construct($product = null, $from = null, $to = null)
    {
        $this->product = UserProductService::isValidFilter($product) ? $product : null;
        $this->from = $from;
        $this->to = $to;

        if ($this->product === 'happilearn') {
            $this->hasViewCount = Schema::hasColumn('recently_viewed_happi_learn_contents', 'view_count');
        }
    }

    public function query()
    {
        $query = User::query()
            ->select('users.*')
            ->with([
                'profileType',
                'couponReceipt.receipt',
                'couponReceipt.coupon',
                'userToken.token.organization' => function ($q) {
                    $q->withTrashed();
                },
            ])
            ->orderByDesc('users.created_at')
            ->orderByDesc('users.id');

        UserProductService::withUsageCounts($query);

        if ($this->product) {
            UserProductService::applyFilter($query, $this->product);
        }
        UserProductService::applyDateRange($query, $this->product, $this->from, $this->to);

        if ($this->hasProduct()) {
            $relation = UserProductService::PRODUCTS[$this->product]['relation'];
            $query->withMin($relation . ' as product_started_at', 'created_at');
        }

        if ($this->product === 'happilearn') {
            $query->withCount(['happilearnViews as contents_viewed_count', 'happilearnLikes as contents_liked_count'])
                ->withMax('happilearnViews as last_viewed_at', 'updated_at');
            if ($this->hasViewCount) {
                $query->withSum('happilearnViews as total_views_count', 'view_count');
            }
        }

        return $query;
    }

    public function headings(): array
    {
        $headings = [
            '#',
            'Username',
            'Nickname',
            'E-mail',
            'Mobile',
            'Type',
            'Organization',
            'Token',
            'Coupon Used',
            'Profession',
            'Account Status',
            'Age',
            'Gender',
            'Products Used',
            'Registered On',
        ];

        if ($this->hasProduct()) {
            $headings[] = 'Started ' . UserProductService::label($this->product) . ' On';
        }

        if ($this->product === 'happilearn') {
            $headings[] = 'Contents Opened';
            if ($this->hasViewCount) {
                $headings[] = 'Total Views';
            }
            $headings[] = 'Contents Liked';
            $headings[] = 'Last Viewed';
        }

        return $headings;
    }

    public function map($user): array
    {
        $token = $user->userToken ? $user->userToken->token : null;
        $organization = $token ? $token->organization : null;

        $row = [
            ++$this->rowNumber,
            $user->username ?? '-',
            $user->nickname ?? '-',
            $user->email ?? '-',
            $user->mobile ?? '-',
            $user->userToken ? 'B2B' : 'B2C',
            $organization ? $organization->name : '-',
            $token ? $token->token : '-',
            $user->getUsedCouponCodes() ?? '-',
            $user->profileType->name ?? '-',
            $user->account_status ?? '-',
            $user->age ?? '-',
            $user->gender ?? '-',
            implode(', ', UserProductService::usedProductLabels($user)) ?: 'Not started',
            $this->formatDate($user->created_at),
        ];

        if ($this->hasProduct()) {
            $row[] = $this->formatDate($user->product_started_at);
        }

        if ($this->product === 'happilearn') {
            $row[] = (int)$user->contents_viewed_count;
            if ($this->hasViewCount) {
                $row[] = (int)$user->total_views_count;
            }
            $row[] = (int)$user->contents_liked_count;
            $row[] = $this->formatDate($user->last_viewed_at);
        }

        return $row;
    }

    private function hasProduct(): bool
    {
        return $this->product && isset(UserProductService::PRODUCTS[$this->product]);
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '-';
        }
        return \Carbon\Carbon::parse($value)->format('d-M-Y h:i a');
    }

    /**
     * File name like "HappiLIFE Users 01-Nov-2026 to 17-Nov-2026.xlsx"
     */
    public static function fileName($product, $from, $to, $extension): string
    {
        $name = $product ? UserProductService::label($product) . ' Users' : 'All Users';
        if ($from || $to) {
            $name .= ' ' . ($from ? $from->format('d-M-Y') : 'start') . ' to ' . ($to ? $to->format('d-M-Y') : 'today');
        } else {
            $name .= ' ' . \Carbon\Carbon::now()->format('d-M-Y g-i a');
        }
        return str_replace(['/', '\\'], '-', $name) . '.' . $extension;
    }
}
