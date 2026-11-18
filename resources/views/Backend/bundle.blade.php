<x-backend-layout>
  <x-slot name="title">
    Manage Plans
  </x-slot>
  <x-slot name="css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="{{ asset('assets/Backend/css/plugins/dataTables.bootstrap.min.css') }}" rel="stylesheet">
    <style>
      .plan-actions form { display:inline; margin:0; }
      .plan-actions .btn { margin:2px 0; }
      .plan-hidden-row td { color:#999; }
      .plan-contents { margin:0; padding-left:16px; }
    </style>
  </x-slot>
  <x-slot name="js">
      <script src="{{ asset('assets/Backend/js/plugins/jquery.dataTables.min.js') }}"></script>
      <script src="{{ asset('assets/Backend/js/plugins/dataTables.bootstrap.min.js') }}"></script>
      <script src="{{ asset('assets/Backend/js/plugins/dataTables.keyTable.min.js') }}"></script>
      <script src="{{ asset('assets/Backend/js/plugins/dataTables.responsive.min.js') }}"></script>
      <script src="{{ asset('assets/Backend/js/plugins/dataTables.scroller.min.js') }}"></script>
  </x-slot>
  <x-slot name="content">
    <div class="right_col" role="main">
      <div class="x_content">
        <div class="x_panel">
          <div class="row" style="margin-bottom:10px;">
            <div class="col-sm-8">
              <h4 style="margin-top:5px;">Plans &amp; Bundles</h4>
              <p class="text-muted font-13">Edit prices, descriptions, validity and bundle contents. Hidden plans cannot be bought, but existing subscribers keep access. HappiTALK prices are managed under Expert Levels.</p>
            </div>
            <div class="col-sm-4 text-right">
              <a href="{{ route('admin.createBundle') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Create Bundle</a>
            </div>
          </div>

          @if(Session::has('error'))
            <div class="alert alert-danger">{{ Session::get('error') }}</div>
          @endif
          @if(Session::has('success'))
            <div class="alert alert-success">{{ Session::get('success') }}</div>
          @endif
          @if(!$supportsActiveFlag)
            <div class="alert alert-warning">Showing/hiding plans needs the latest database migration (<code>php artisan migrate</code>).</div>
          @endif

          @if(isset($packages))
            <div class="row">
              <div class="col-sm-12">
                <div class="card-box table-responsive">
                  <div class="x_content">
                    <table id="datatable" class="table table-striped table-bordered" style="width:100%">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Name</th>
                          <th>Type</th>
                          <th>Description</th>
                          <th>Includes</th>
                          <th>Duration</th>
                          <th>Regular Price</th>
                          <th>Discount (%)</th>
                          <th>In-offer Price</th>
                          <th>Validity</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($packages as $package)
                          @if($package->package && !in_array($package->package->name, ['HappiTALK', 'HappiAPP', 'HappiBUDDY+ HappiAPP']))
                            <?php
                              $name = \App\Http\Controllers\PlanManagementController::displayName($package->package->name);
                              $isBundle = (bool)$package->package->bundle;
                              $isActive = !$supportsActiveFlag || $package->package->is_active;
                            ?>
                            <tr class="{{ $isActive ? '' : 'plan-hidden-row' }}">
                              <td>{{ $package->id }}</td>
                              <td><strong>{{ $name }}</strong></td>
                              <td>
                                @if($isBundle)
                                  <span class="label label-info">Bundle</span>
                                @else
                                  <span class="label label-default">Single service</span>
                                @endif
                              </td>
                              <td>{{ $package->package->description }}</td>
                              <td>
                                @if($isBundle && !empty($bundleContents[$package->package->id]))
                                  <ul class="plan-contents">
                                    @foreach($bundleContents[$package->package->id] as $item)
                                      <li>{{ $item }}</li>
                                    @endforeach
                                  </ul>
                                @else
                                  -
                                @endif
                              </td>
                              <td>{{ $package->duration ? $package->duration->name : '-' }}</td>
                              <td>{{ $package->price }}</td>
                              @if($package->offer)
                                <td>{{ $package->offer->discount }}</td>
                                <td>{{ $package->offer->price }}</td>
                              @else
                                <td>No Offer</td>
                                <td>{{ $package->price }}</td>
                              @endif
                              <td>{{ $package->package->validity ? $package->package->validity . ' days' : 'Lifetime' }}</td>
                              <td>
                                @if($isActive)
                                  <span class="label label-success">Visible</span>
                                @else
                                  <span class="label label-warning">Hidden</span>
                                @endif
                              </td>
                              <td class="plan-actions" style="white-space:nowrap;">
                                <a href="{{ route('admin.plans.edit', $package->id) }}" class="btn btn-xs btn-primary" title="Edit"><i class="fa fa-edit"></i> Edit</a>
                                @if($supportsActiveFlag)
                                  <form method="POST" action="{{ route('admin.plans.toggleStatus', $package->id) }}"
                                        onsubmit="return confirm('{{ $isActive ? 'Hide this plan? Customers will no longer be able to buy it.' : 'Make this plan visible to customers again?' }}');">
                                    @csrf
                                    <button type="submit" class="btn btn-xs {{ $isActive ? 'btn-warning' : 'btn-success' }}">
                                      <i class="fa {{ $isActive ? 'fa-eye-slash' : 'fa-eye' }}"></i> {{ $isActive ? 'Hide' : 'Show' }}
                                    </button>
                                  </form>
                                @endif
                                @if($isBundle)
                                  <form method="POST" action="{{ route('admin.plans.destroy', $package->id) }}"
                                        onsubmit="return confirm('Delete this bundle permanently? This only works if nobody has bought it.');">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Delete</button>
                                  </form>
                                @endif
                              </td>
                            </tr>
                          @endif
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>
  </x-slot>
</x-backend-layout>
