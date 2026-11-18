<?php

namespace App\Http\Controllers;

use App\Models\DynamicBundlePlan;
use App\Models\Offer;
use App\Models\Package;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin Panel > Plans: edit, show/hide and delete plans and bundles.
 *
 * Single services (HappiSELF, HappiBUDDY, ...) are looked up by name in many places,
 * so only their description, prices and validity can be edited here. Bundles can be
 * fully edited, and deleted while nobody has bought them.
 */
class PlanManagementController extends Controller
{
    private const ROLES = ['admin', 'super-admin', 'plans'];

    /** Managed elsewhere (HappiTALK prices come from expert levels) or not sold any more. */
    private const UNMANAGED_PACKAGES = ['HappiTALK', 'HappiAPP', 'HappiBUDDY+ HappiAPP'];

    private function authorizeAdmin()
    {
        $admin = auth('admin')->user();
        if (!$admin || !$admin->hasAnyRole(self::ROLES)) {
            abort(403);
        }
    }

    /**
     * Name shown to admins (some packages keep legacy names in the database).
     */
    public static function displayName(string $name): string
    {
        $aliases = [
            'HappiLIFE Summary Reading' => 'HappiLEARN',
            'HappiLIFE Screening' => 'HappiLIFE Awareness Tool',
        ];
        if (strtolower($name) === 'happiguide') {
            return 'Solv';
        }
        return $aliases[$name] ?? $name;
    }

    private function findManagedPlan($id): Plan
    {
        $plan = Plan::with(['package', 'duration', 'offer' => function ($query) {
            $query->where('valid', true)->orderBy('created_at', 'desc');
        }])->findOrFail($id);

        if (!$plan->package || in_array($plan->package->name, self::UNMANAGED_PACKAGES)) {
            abort(404);
        }
        return $plan;
    }

    /**
     * Single services that can be put inside a bundle (one plan id each).
     */
    private function bundleableServices()
    {
        return Package::where('bundle', '!=', 1)->with('plan')->get()
            ->filter(function ($package) {
                return $package->plan->isNotEmpty();
            });
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAdmin();
        $plan = $this->findManagedPlan($id);
        $package = $plan->package;

        $includedPlans = $package->bundle
            ? DynamicBundlePlan::where('package_id', $package->id)->pluck('sessions', 'plan_id')->toArray()
            : [];

        return view('Backend.plans.editPlan')
            ->with('plan', $plan)
            ->with('package', $package)
            ->with('displayName', self::displayName($package->name))
            ->with('services', $package->bundle ? $this->bundleableServices() : collect())
            ->with('includedPlans', $includedPlans)
            ->with('purchaseCount', $this->purchaseCount($plan));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAdmin();
        $plan = $this->findManagedPlan($id);
        $package = $plan->package;
        $isBundle = (bool)$package->bundle;

        $rules = [
            'description' => 'required|string|max:2000',
            'price' => 'required|numeric|min:0',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'discounted_price' => 'required|numeric|min:0',
            'validity' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
        if ($isBundle) {
            $rules['name'] = 'required|string|max:255';
            $rules['plans'] = 'required|array|min:1';
            $rules['plans.*'] = 'integer';
            $rules['plan_sessions'] = 'nullable|array';
            $rules['plan_sessions.*'] = 'nullable|integer|min:1';
        }
        $data = $request->validate($rules, [
            'plans.required' => 'Please select at least one service for this bundle.',
        ]);

        if ((float)$data['discounted_price'] > (float)$data['price']) {
            throw ValidationException::withMessages(['discounted_price' => 'Discounted price cannot be more than the regular price.']);
        }

        $selectedPlanIds = [];
        if ($isBundle) {
            $allowedPlanIds = $this->bundleableServices()->map(function ($service) {
                return $service->plan[0]->id;
            })->all();
            $selectedPlanIds = array_values(array_unique(array_map('intval', $data['plans'])));
            if (array_diff($selectedPlanIds, $allowedPlanIds)) {
                throw ValidationException::withMessages(['plans' => 'One of the selected services is not available.']);
            }
        }

        DB::transaction(function () use ($data, $plan, $package, $isBundle, $selectedPlanIds) {
            $packageData = [
                'description' => $data['description'],
                'validity' => isset($data['validity']) && $data['validity'] !== '' ? (int)$data['validity'] : null,
            ];
            if ($isBundle) {
                $packageData['name'] = $data['name'];
            }
            if (Package::supportsActiveFlag()) {
                $packageData['is_active'] = !empty($data['is_active']);
            }
            $package->update($packageData);

            $plan->price = $data['price'];
            $plan->save();

            $offer = $plan->offer;
            if ($offer) {
                $offer->update(['discount' => $data['discount_percentage'], 'price' => $data['discounted_price']]);
            } else {
                Offer::create([
                    'name' => 'starting',
                    'discount' => $data['discount_percentage'],
                    'price' => $data['discounted_price'],
                    'valid' => 1,
                    'plan_id' => $plan->id,
                ]);
            }

            if ($isBundle) {
                // Changes apply to future purchases; people who already bought the
                // bundle keep the services they were given.
                $sessions = $data['plan_sessions'] ?? [];
                DynamicBundlePlan::where('package_id', $package->id)->whereNotIn('plan_id', $selectedPlanIds)->delete();
                foreach ($selectedPlanIds as $planId) {
                    DynamicBundlePlan::updateOrCreate(
                        ['package_id' => $package->id, 'plan_id' => $planId],
                        ['sessions' => isset($sessions[$planId]) && $sessions[$planId] !== '' ? (int)$sessions[$planId] : null]
                    );
                }
            }
        });

        return redirect()->route('admin.bundleDetail')
            ->with('success', '"' . self::displayName($package->fresh()->name) . '" updated successfully.');
    }

    public function toggleStatus(Request $request, $id)
    {
        $this->authorizeAdmin();
        if (!Package::supportsActiveFlag()) {
            return redirect()->route('admin.bundleDetail')
                ->with('error', 'Run "php artisan migrate" first to enable showing/hiding plans.');
        }
        $plan = $this->findManagedPlan($id);
        $package = $plan->package;
        $package->update(['is_active' => !$package->is_active]);

        $name = self::displayName($package->name);
        return redirect()->route('admin.bundleDetail')->with('success', $package->is_active
            ? '"' . $name . '" is now visible to customers.'
            : '"' . $name . '" is now hidden. Customers cannot buy it; existing subscribers keep access.');
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeAdmin();
        $plan = $this->findManagedPlan($id);
        $package = $plan->package;
        $name = self::displayName($package->name);

        if (!$package->bundle) {
            return redirect()->route('admin.bundleDetail')
                ->with('error', 'Single services cannot be deleted. Hide "' . $name . '" instead.');
        }
        if ($this->purchaseCount($plan) > 0 || $this->isReferenced($plan)) {
            return redirect()->route('admin.bundleDetail')
                ->with('error', '"' . $name . '" has been bought or is linked to coupons/organisation codes, so it cannot be deleted. Hide it instead.');
        }

        DB::transaction(function () use ($plan, $package) {
            DynamicBundlePlan::where('package_id', $package->id)->delete();
            Offer::where('plan_id', $plan->id)->delete();
            $package->delete(); // soft delete
        });

        return redirect()->route('admin.bundleDetail')->with('success', '"' . $name . '" deleted.');
    }

    /**
     * Number of purchases of this plan (receipts).
     */
    private function purchaseCount(Plan $plan): int
    {
        return (int)DB::table('receipt_packages')->where('plan_id', $plan->id)->count();
    }

    private function isReferenced(Plan $plan): bool
    {
        return DB::table('bundle_statuses')->where('plan_id', $plan->id)->exists()
            || DB::table('token_plans')->where('plan_id', $plan->id)->exists()
            || DB::table('coupon_plans')->where('plan_id', $plan->id)->exists()
            || DB::table('dynamic_bundle_plans')->where('plan_id', $plan->id)->exists();
    }
}
