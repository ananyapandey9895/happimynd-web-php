{{--
  From / To date filter with Excel / CSV download, used on the View Users pages.
  Include inside a GET <form> whose action is the list page.
    $downloadRoute : URL of the download action (receives the same fields + format)
    $dateHint      : what the dates filter on
    $fromDate / $toDate : Carbon|null currently applied
    $clearUrl      : list URL without the date filter
--}}
<div class="user-date-filter" style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px; margin-bottom:8px;">
  <div>
    <label for="from_date" style="display:block; font-weight:500;">From Date</label>
    <input type="date" id="from_date" name="from_date" class="form-control"
           value="{{ $fromDate ? $fromDate->format('Y-m-d') : '' }}" max="{{ now()->format('Y-m-d') }}">
  </div>
  <div>
    <label for="to_date" style="display:block; font-weight:500;">To Date</label>
    <input type="date" id="to_date" name="to_date" class="form-control"
           value="{{ $toDate ? $toDate->format('Y-m-d') : '' }}" max="{{ now()->format('Y-m-d') }}">
  </div>
  <div>
    <button type="submit" class="btn btn-primary" style="margin:0;">Apply</button>
    @if($fromDate || $toDate)
      <a href="{{ $clearUrl }}" class="btn btn-default" style="margin:0;">Clear dates</a>
    @endif
  </div>
  <div style="margin-left:auto;">
    <button type="submit" formaction="{{ $downloadRoute }}" name="format" value="xlsx" class="btn btn-success" style="margin:0;">
      <i class="fa fa-file-excel-o"></i> Download Excel
    </button>
    <button type="submit" formaction="{{ $downloadRoute }}" name="format" value="csv" class="btn btn-default" style="margin:0;">
      <i class="fa fa-download"></i> Download CSV
    </button>
  </div>
</div>
<p class="text-muted" style="font-size:12px; margin-bottom:15px;">
  {{ $dateHint }} Leave both dates empty to list / download everyone.
</p>
