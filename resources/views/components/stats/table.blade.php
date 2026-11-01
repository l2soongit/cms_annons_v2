{{--
    Таблиця «топ значень» зі смужкою частки: однаково в адмінці й кабінеті.
    rows: [['label' => …, 'values' => [число, …], 'share' => відсоток, 'href' => ?], …]; columns: назви числових стовпців.
--}}
@props(['rows', 'columns', 'title' => null, 'empty' => '—', 'color' => '#6366f1'])
<div {{ $attributes }}>
    @if ($title)<h3 style="font-size:14px;font-weight:600;margin:0 0 10px">{{ $title }}</h3>@endif
    @if (collect($rows)->isEmpty())
        <p style="font-size:13px;opacity:.6;margin:0">{{ $empty }}</p>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="opacity:.6;font-size:12px">
                    <th style="text-align:left;font-weight:500;padding:0 0 6px"></th>
                    @foreach ($columns as $c)<th style="text-align:right;font-weight:500;padding:0 0 6px 10px;white-space:nowrap">{{ $c }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td style="padding:5px 0;position:relative;max-width:0;width:100%">
                            <span style="position:absolute;inset:2px auto 2px 0;width:{{ max(0.5, min(100, $row['share'] ?? 0)) }}%;background:{{ $color }};opacity:.14;border-radius:4px"></span>
                            <span style="position:relative;display:block;padding:0 6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $row['label'] }}">
                                @if (! empty($row['href']))<a href="{{ $row['href'] }}" target="_blank" rel="noopener" style="color:inherit;text-decoration:none">{{ $row['label'] }}</a>@else{{ $row['label'] }}@endif
                            </span>
                        </td>
                        @foreach ($row['values'] as $i => $v)
                            <td style="text-align:right;padding:5px 0 5px 10px;white-space:nowrap;font-variant-numeric:tabular-nums;{{ $i > 0 ? 'opacity:.7' : '' }}">{{ is_numeric($v) ? number_format((float) $v, is_float($v) && floor($v) != $v ? 1 : 0, ',', ' ') : $v }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
