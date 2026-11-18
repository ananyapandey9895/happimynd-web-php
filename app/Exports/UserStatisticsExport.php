<?php

namespace App\Exports;

use App\Models\User;
use App\Services\UserProductService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Overall user statistics: one row for all users and one per service.
 * Test users are excluded, like the dashboard tiles.
 */
class UserStatisticsExport implements FromArray, WithHeadings, ShouldAutoSize
{
    protected $from;
    protected $to;

    public function __construct($from = null, $to = null)
    {
        $this->from = $from;
        $this->to = $to;
    }

    protected function hasRange(): bool
    {
        return (bool)($this->from || $this->to);
    }

    public function headings(): array
    {
        $headings = ['Service', 'Total Users', 'B2C Users', 'B2B Users', 'New Users (last 24 hours)', 'New Users (last 7 days)'];
        if ($this->hasRange()) {
            $headings[] = 'New Users (' . $this->rangeLabel() . ')';
        }
        return $headings;
    }

    public function array(): array
    {
        $newUsers = UserProductService::newUserStats();
        $breakdown = UserProductService::dashboardBreakdown();
        $range = $this->hasRange() ? UserProductService::newUserCountsBetween($this->from, $this->to) : [];

        $b2b = User::RemoveTestUser()->has('userToken')->count();
        $total = User::RemoveTestUser()->count();

        $overall = [
            'All Users (registrations)',
            $total,
            $total - $b2b,
            $b2b,
            $newUsers['overall']['24h'],
            $newUsers['overall']['7d'],
        ];
        if ($this->hasRange()) {
            $overall[] = $range['overall'];
        }
        $rows = [$overall];

        foreach (UserProductService::PRODUCTS as $key => $config) {
            $row = [
                $config['label'],
                $breakdown[$key]['total'] ?? 0,
                $breakdown[$key]['b2c'] ?? 0,
                $breakdown[$key]['b2b'] ?? 0,
                $newUsers['products'][$key]['24h'] ?? 0,
                $newUsers['products'][$key]['7d'] ?? 0,
            ];
            if ($this->hasRange()) {
                $row[] = $range[$key] ?? 0;
            }
            $rows[] = $row;
        }

        $rows[] = [];
        $rows[] = ['Generated on ' . Carbon::now()->format('d-M-Y h:i a') . '. Test users excluded.'];
        $rows[] = ['"New" for a service = the user started that service (first use) in the period; for All Users = registered in the period.'];

        return $rows;
    }

    protected function rangeLabel(): string
    {
        return ($this->from ? $this->from->format('d-M-Y') : 'start') . ' to ' . ($this->to ? $this->to->format('d-M-Y') : 'today');
    }

    public function fileName($extension): string
    {
        $name = 'User Statistics';
        $name .= $this->hasRange() ? ' ' . $this->rangeLabel() : ' ' . Carbon::now()->format('d-M-Y g-i a');
        return $name . '.' . $extension;
    }
}
