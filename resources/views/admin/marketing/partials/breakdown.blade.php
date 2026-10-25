{{-- A "top N" table with share bars. Expects: $title, $rows, $total, optional $label (closure), $link (closure), $empty --}}
@php $label ??= fn ($row) => e($row->label); @endphp
<div class="card">
    <div class="card-head"><h3>{{ $title }}</h3>@isset($note)<span class="muted" style="font-size:12px">{{ $note }}</span>@endisset</div>
    @if ($rows->isEmpty())
        <div class="empty" style="padding:30px 20px">{{ $empty ?? 'No data yet for this period.' }}</div>
    @else
        <div class="table-wrap">
            <table class="table breakdown">
                <thead><tr><th>{{ $column ?? 'Name' }}</th><th class="num">Visits</th><th class="num">Orders</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $share = $total ? round($row->visits / $total * 100) : 0; @endphp
                        <tr>
                            <td>
                                <div class="bd-label">@isset($link)<a href="{{ $link($row) }}">{!! $label($row) !!}</a>@else{!! $label($row) !!}@endisset</div>
                                <div class="bd-bar"><span style="width:{{ max(2, $share) }}%"></span></div>
                            </td>
                            <td class="num">{{ number_format($row->visits) }}<div class="cell-sub">{{ $share }}%</div></td>
                            <td class="num">{{ number_format($row->orders) }}@if ($row->revenue > 0)<div class="cell-sub">{{ money($row->revenue) }}</div>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
