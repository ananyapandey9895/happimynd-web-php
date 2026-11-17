<x-backend-layout>
    <x-slot name="title">
        Customer list
    </x-slot>
    <x-slot name="css">
        <link href="cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css">
        <link href="{{ asset('assets/Backend/css/plugins/nprogress.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/Backend/css/plugins/dataTables.bootstrap.min.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/Backend/css/plugins/buttons.bootstrap.min.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/Backend/css/plugins/fixedHeader.bootstrap.min.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/Backend/css/plugins/responsive.bootstrap.min.css') }}" rel="stylesheet">
    </x-slot>
    <x-slot name="js">
        <script src="{{ asset('assets/Backend/js/plugins/fastclick.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/nprogress.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/icheck.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/dataTables.bootstrap.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/dataTables.buttons.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/buttons.bootstrap.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/buttons.flash.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/buttons.html5.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/buttons.print.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/dataTables.fixedHeader.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/dataTables.keyTable.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/dataTables.responsive.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/responsive.bootstrap.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/dataTables.scroller.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/jszip.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/pdfmake.min.js') }}"></script>
        <script src="{{ asset('assets/Backend/js/plugins/vfs_fonts.js') }}"></script>
    </x-slot>
    <x-slot name="content">
        <div class="right_col" role="main">
        <div class="row">
        <div class="col-md-12 col-sm-12 ">
            <div class="x_panel">
              <div class="x_title">
                <h2>Users @if($product) - {{ \App\Services\UserProductService::label($product) }} @endif ({{ $users->total() }})</h2>
                <div class="clearfix"></div>
              </div>
              <div class="x_content">
                  <div class="row">
                      <div class="col-sm-12">
                        <div class="card-box table-responsive custom-change-table">
                          {{-- Product classification: all users, or only users who started a product --}}
                          <div style="margin-bottom:15px; display:flex; flex-wrap:wrap; gap:6px;">
                            <a href="{{ route('admin.customerListView') }}"
                               class="btn btn-sm {{ !$product ? 'btn-primary' : 'btn-default' }}">
                              All Users ({{ $productCounts['all'] ?? 0 }})
                            </a>
                            @foreach($products as $key => $config)
                            <a href="{{ route('admin.customerListView', ['product' => $key]) }}"
                               class="btn btn-sm {{ $product === $key ? 'btn-primary' : 'btn-default' }}">
                              {{ $config['label'] }} ({{ $productCounts[$key] ?? 0 }})
                            </a>
                            @endforeach
                            <a href="{{ route('admin.customerListView', ['product' => 'none']) }}"
                               class="btn btn-sm {{ $product === 'none' ? 'btn-primary' : 'btn-default' }}">
                              Not started any product ({{ $productCounts['none'] ?? 0 }})
                            </a>
                          </div>
                          <p class="text-muted" style="font-size:12px;">A user is listed under every product they have started. Counts refresh every 10 minutes.</p>


                {{-- From / To date filter + Excel / CSV download of the users shown --}}
                <form method="GET" action="{{ route('admin.customerListView') }}">
                  @if($product)<input type="hidden" name="product" value="{{ $product }}">@endif
                  @if(request('per_page'))<input type="hidden" name="per_page" value="{{ (int)request('per_page') }}">@endif
                  @include('Backend.includes.user_date_filter', [
                    'downloadRoute' => route('admin.downloadUserListXL'),
                    'clearUrl'      => route('admin.customerListView', array_filter(['product' => $product])),
                    'dateHint'      => ($product && $product !== 'none')
                                        ? 'Dates filter on when the user started ' . \App\Services\UserProductService::label($product) . ' (their first use), i.e. users newly added to this service in the range.'
                                        : 'Dates filter on the user\'s registration date.',
                  ])
                </form>

                <table id="datatable-buttons" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Username</th>
                      <th>Nickname</th>
                      <th>Coupon Used</th>
                      <th>Organization</th>
                      <th>Token</th>
                      <th>E-mail</th>
                      <th>Mobile</th>
                      <th>Profession</th>
                      <th>Account Status</th>
                      <th>Age</th>
                      <th>Gender</th>
                      <th>Products Used</th>
                      <th>Registered On</th>
                      @if($product && $product !== 'none')<th>Started {{ \App\Services\UserProductService::label($product) }} On</th>@endif
                      <th>Rating</th>
                      <th>Review</th>
                      <th style="text-align:center;">Action</th>

                    </tr>
                  </thead>
                  <tbody>
                      @foreach($users as $user)

                      <?php  

                        if($user->usersRating != null){
                          $emoji_path = $user->usersRating->applicationRatingEmoji->image;
                        }else{
                          $emoji_path = '';
                        }

                      ?>
                      <?php
                        // relations are eager loaded in the controller (no per-row queries)
                        $userTokenRow = $user->userToken ? $user->userToken->token : null;
                        $organizationRow = $userTokenRow ? $userTokenRow->organization : null;
                      ?>
                        <tr>
                        <td>{{ $users->firstItem() + $loop->index }}</td>
                        <td>{{ $user->username ?? '-' }}</td>
                        <td>{{ $user->nickname ?? '-' }}</td>
                        <td>{{ $user->getUsedCouponCodes()??'-'}}</td>
                        <td>{{ $organizationRow->name ?? '-' }}</td>
                        <td>{{ $userTokenRow->token ?? '-' }}</td>
                        <td>{{ $user->email ?? '-' }}</td>
                        <td>{{ $user->mobile ?? '-' }}</td>
                        <td>{{ $user->profileType->name ?? '-' }}</td>
                        <td>{{ $user->account_status ?? '-' }}</td>
                        <td>{{ $user->age ?? '-' }}</td>
                        <td>{{ $user->gender ?? '-'}}</td>
                        <td>{{ implode(', ', \App\Services\UserProductService::usedProductLabels($user)) ?: 'Not started' }}</td>
                        <td> {{ $user->created_at ? $user->created_at->format('M d,Y h:i a') : '-' }}</td>
                        @if($product && $product !== 'none')
                        <td>{{ $user->product_started_at ? \Carbon\Carbon::parse($user->product_started_at)->format('M d,Y h:i a') : '-' }}</td>
                        @endif
                          @if($user->usersRating != null)
                          <td>
                            <img src="{{$emoji_path}}">
                          </td>
                          @else
                          <td>-</td>
                          @endif
                        <td>{{ $user->usersRating->review ?? '-'}}</td>
                        <td>
                          <a href="{{url('admin/view-user-mood').'/'.$user->id}}" class="btn btn-primary" >View Mood</a>
                          <a href="{{url('admin/view-user-rewards').'/'.$user->id}}" class="btn btn-primary">View Rewards</a>
                        </td>

                        </tr>
                        @endforeach
                  </tbody>
                </table>

                <x-pagination-dropdown :paginator="$users" />

              </div>
            </div>
          </div>
            </div>


    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
      function validateDate(start,end){
        const _MS_PER_DAY = 1000 * 60 * 60 * 24;
        const start_date = new Date('"'+start.value+'"'),
        end_date = new Date('"'+end.value+'"');
        const utc1 = Date.UTC(start_date.getFullYear(), start_date.getMonth(), start_date.getDate());
        const utc2 = Date.UTC(end_date.getFullYear(), end_date.getMonth(), end_date.getDate());
        if(Math.floor((utc2 - utc1) / _MS_PER_DAY)>31){
            alert("End Date must be atmost 30 days from start date");
            return(false);
          }     
      }
    </script>

    </x-slot>
</x-backend-layout>
