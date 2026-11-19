<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'packages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'bundle',
        'duration_id',
        'regular_price',
        'package_id',
        'validity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    private static $hasIsActiveColumn = null;

    /**
     * True when the is_active migration has run. Lets the code deploy before
     * `php artisan migrate` without breaking the plan listings.
     */
    public static function supportsActiveFlag(): bool
    {
        if (self::$hasIsActiveColumn === null) {
            self::$hasIsActiveColumn = \Illuminate\Support\Facades\Schema::hasColumn('packages', 'is_active');
        }
        return self::$hasIsActiveColumn;
    }

    /**
     * Only plans that admins have not hidden (what customers may see and buy).
     */
    public function scopeVisible($query)
    {
        if (self::supportsActiveFlag()) {
            $query->where($this->getTable() . '.is_active', 1);
        }
        return $query;
    }

    public function isVisible(): bool
    {
        return !self::supportsActiveFlag() || (bool)$this->is_active;
    }

    /**
     * Whether the given plan id belongs to a plan admins have not hidden.
     * Unknown plan ids return true so existing validation keeps handling them.
     */
    public static function planIsPurchasable($planId): bool
    {
        $plan = Plan::find($planId);
        if (!$plan) {
            return true;
        }
        $package = self::withTrashed()->find($plan->package_id);
        return $package && !$package->trashed() && $package->isVisible();
    }

    public function plan()
    {
        return $this->hasMany(Plan::class);
    }

    public function bundlePlans()
    {
        return $this->hasMany(DynamicBundlePlan::class, 'package_id');
    }

    public function offer()
    {
        return $this->hasOneThrough(Plan::class, Offer::class);
    }

    public function duration()
    {
        return $this->belongsTo(Duration::class);
    }

    public function getMinimumPricePlan()
    {
        $plans = $this->plan()->whereHas('expertLevel')->with('expertLevel')->get();
        $minPrice = null;
        foreach ($plans as $key2 => $plan) {
            if ($plan->expertLevel) {
                if ($minPrice == null) {
                    $minPrice = $plan;
                } else if ($plan->getPerSessionSellingPrice() < $minPrice->getPerSessionSellingPrice()) {
                    $minPrice = $plan;
                }
            }
        }
        return $minPrice;
    }




    public function mobilePlans(){
        return $this->hasMany(Plan::class , 'package_id')->select('id','package_id','duration_type_id','price','expert_level_id')->where('active',1)->with('mobileOffers','mobileExpertLevel','mobileDuration');
    }



    // public function getNameAttribute($value){
    //     if($value == 'HappiLIFE Summary Reading'){
    //         return "HappiLEARN";
    //     }else{
    //         return $value;
    //     }
    // }



}





