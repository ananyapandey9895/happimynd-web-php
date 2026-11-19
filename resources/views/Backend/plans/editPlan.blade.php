<x-backend-layout>
  <x-slot name="title">
    Edit Plan
  </x-slot>
  <x-slot name="css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </x-slot>
  <x-slot name="js">
    <script>
      function toggleSessions(checkbox, planId) {
        var wrap = document.getElementById('sessions_wrap_' + planId);
        if (!wrap) return;
        var input = wrap.querySelector('input');
        wrap.style.display = checkbox.checked ? '' : 'none';
        input.disabled = !checkbox.checked;
      }

      // Keep discount %, regular price and in-offer price in step.
      function recalcOfferPrice() {
        var price = parseFloat(document.getElementById('price').value);
        var discount = parseFloat(document.getElementById('discount_percentage').value);
        if (!isNaN(price) && !isNaN(discount)) {
          document.getElementById('discounted_price').value = Math.round(price - (price * discount / 100));
        }
      }
      function recalcDiscount() {
        var price = parseFloat(document.getElementById('price').value);
        var offer = parseFloat(document.getElementById('discounted_price').value);
        if (!isNaN(price) && price > 0 && !isNaN(offer)) {
          document.getElementById('discount_percentage').value = Math.max(0, Math.round((1 - offer / price) * 100));
        }
      }

      function validatePlanForm() {
        var plans = document.querySelectorAll('input[name="plans[]"]');
        if (plans.length && document.querySelectorAll('input[name="plans[]"]:checked').length === 0) {
          alert('Please select at least one service for this bundle.');
          return false;
        }
        var price = parseFloat(document.getElementById('price').value);
        var offer = parseFloat(document.getElementById('discounted_price').value);
        if (offer > price) {
          alert('Discounted price cannot be more than the regular price.');
          return false;
        }
        return true;
      }
    </script>
  </x-slot>
  <x-slot name="content">
    <div class="right_col" role="main">
      <div class="x_content">
        <div class="x_panel">
          <div class="row">
            <div class="col-sm-12">
              <a href="{{ route('admin.bundleDetail') }}"><i class="fa fa-arrow-left"></i> Back to plans</a>
              <h4>
                Edit {{ $package->bundle ? 'Bundle' : 'Plan' }}: {{ $displayName }}
                @if(\App\Models\Package::supportsActiveFlag() && !$package->is_active)
                  <span class="label label-warning">Hidden</span>
                @endif
              </h4>
              @if($purchaseCount > 0)
                <div class="alert alert-info">
                  This {{ $package->bundle ? 'bundle' : 'plan' }} has been bought {{ $purchaseCount }} time(s).
                  Changes apply to new purchases only; existing subscribers keep what they bought.
                </div>
              @endif

              @if ($errors->any())
                <div class="alert alert-danger">
                  <ul style="margin:0;">
                    @foreach ($errors->all() as $error)
                      <li>{{ $error }}</li>
                    @endforeach
                  </ul>
                </div>
              @endif

              <form method="POST" action="{{ route('admin.plans.update', $plan->id) }}" onsubmit="return validatePlanForm()">
                @csrf
                <div class="x_panel">

                  <div class="form-group">
                    <label>Name</label>
                    @if($package->bundle)
                      <input type="text" class="form-control" name="name" value="{{ old('name', $package->name) }}" maxlength="255" required>
                    @else
                      <input type="text" class="form-control" value="{{ $displayName }}" disabled>
                      <small class="text-muted">Single service names are used by the app and cannot be changed here.</small>
                    @endif
                  </div>

                  <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="description" rows="3" maxlength="2000" required>{{ old('description', $package->description) }}</textarea>
                  </div>

                  @if($package->bundle)
                    <div class="form-group">
                      <label>Services included in this bundle</label>
                      <div style="border:1px solid #e6e6e6; padding:10px 15px; border-radius:4px;">
                        @foreach($services as $service)
                          <?php
                            $servicePlanId = $service->plan[0]->id;
                            $checked = in_array($servicePlanId, old('plans', array_keys($includedPlans)));
                            $sessions = old('plan_sessions.' . $servicePlanId, $includedPlans[$servicePlanId] ?? '');
                            $isTalk = strtolower($service->name) === 'happitalk';
                          ?>
                          <div class="checkbox">
                            <label style="font-weight:normal;">
                              <input type="checkbox" name="plans[]" value="{{ $servicePlanId }}" {{ $checked ? 'checked' : '' }}
                                     @if($isTalk) onchange="toggleSessions(this, {{ $servicePlanId }})" @endif>
                              {{ \App\Http\Controllers\PlanManagementController::displayName($service->name) }}
                            </label>
                            @if($isTalk)
                              <span id="sessions_wrap_{{ $servicePlanId }}" style="margin-left:25px; {{ $checked ? '' : 'display:none;' }}">
                                <label style="font-weight:normal;">Number of sessions</label>
                                <input type="number" class="form-control" name="plan_sessions[{{ $servicePlanId }}]" min="1"
                                       value="{{ $sessions }}" placeholder="e.g. 5" style="width:150px; display:inline-block;" {{ $checked ? '' : 'disabled' }}>
                              </span>
                            @endif
                          </div>
                        @endforeach
                      </div>
                    </div>
                  @endif

                  <div class="row">
                    <div class="col-md-4 form-group">
                      <label>Regular Price</label>
                      <input type="number" step="0.01" min="0" class="form-control" id="price" name="price"
                             value="{{ old('price', $plan->price) }}" oninput="recalcOfferPrice()" required>
                    </div>
                    <div class="col-md-4 form-group">
                      <label>Discount (%)</label>
                      <input type="number" step="0.01" min="0" max="100" class="form-control" id="discount_percentage" name="discount_percentage"
                             value="{{ old('discount_percentage', $plan->offer ? $plan->offer->discount : 0) }}" oninput="recalcOfferPrice()" required>
                    </div>
                    <div class="col-md-4 form-group">
                      <label>In-offer (Discounted) Price</label>
                      <input type="number" step="0.01" min="0" class="form-control" id="discounted_price" name="discounted_price"
                             value="{{ old('discounted_price', $plan->offer ? $plan->offer->price : $plan->price) }}" oninput="recalcDiscount()" required>
                    </div>
                  </div>

                  <div class="form-group">
                    <label>Validity (in days)</label>
                    <input type="number" class="form-control" name="validity" min="1"
                           value="{{ old('validity', $package->validity) }}" placeholder="e.g. 30 for 1 month. Leave blank for lifetime">
                  </div>

                  @if(\App\Models\Package::supportsActiveFlag())
                    <div class="checkbox">
                      <label>
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }}>
                        Visible to customers (uncheck to hide; existing subscribers keep access)
                      </label>
                    </div>
                  @endif

                  <br>
                  <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
                  <a href="{{ route('admin.bundleDetail') }}" class="btn btn-default">Cancel</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </x-slot>
</x-backend-layout>
