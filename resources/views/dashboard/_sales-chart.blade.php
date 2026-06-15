{{--
    Dependency-free sales-by-period bar chart.
    Expects: $salesByDay, $salesByMonth (arrays of ['label','total','count'])
             $currency (string)
--}}
<div class="bg-white rounded-lg shadow-sm p-5 mb-6"
     x-data="{
        period: 'day',
        currency: @js($currency),
        series: {
            day: @js($salesByDay),
            month: @js($salesByMonth),
        },
        get rows() { return this.series[this.period] ?? []; },
        get max() {
            return this.rows.reduce((m, r) => Math.max(m, Number(r.total) || 0), 0);
        },
        get periodTotal() {
            return this.rows.reduce((s, r) => s + (Number(r.total) || 0), 0);
        },
        get periodCount() {
            return this.rows.reduce((s, r) => s + (Number(r.count) || 0), 0);
        },
        get hasData() { return this.periodTotal > 0 || this.periodCount > 0; },
        barHeight(value) {
            if (this.max <= 0) return 0;
            return Math.max(2, (Number(value) || 0) / this.max * 100);
        },
        money(value) {
            const n = Number(value) || 0;
            return this.currency + ' ' + n.toLocaleString(undefined, {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });
        },
     }">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div>
            <h2 class="font-semibold text-slate-800">Sales over time</h2>
            <p class="text-sm text-slate-500 mt-1">
                <span class="font-semibold text-indigo-600" x-text="money(periodTotal)"></span>
                <span class="text-slate-400">·</span>
                <span x-text="periodCount"></span> transactions
                <span class="text-slate-400" x-text="period === 'day' ? 'in the last 14 days' : 'in the last 12 months'"></span>
            </p>
        </div>
        <div class="inline-flex rounded-md border border-slate-200 p-0.5 text-xs shrink-0">
            <button type="button" @click="period = 'day'"
                    :class="period === 'day' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                    class="px-3 py-1.5 rounded">Daily (14d)</button>
            <button type="button" @click="period = 'month'"
                    :class="period === 'month' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                    class="px-3 py-1.5 rounded">Monthly (12m)</button>
        </div>
    </div>

    {{-- Empty state --}}
    <template x-if="!hasData">
        <div class="h-48 flex flex-col items-center justify-center text-center text-slate-400">
            <p class="text-sm">No sales recorded for this period yet.</p>
            <p class="text-xs mt-1">Bars will appear here once you make sales.</p>
        </div>
    </template>

    {{-- Chart --}}
    <template x-if="hasData">
        <div>
            <div class="flex items-end gap-1 h-48 border-b border-slate-100">
                <template x-for="(row, i) in rows" :key="period + '-' + i">
                    <div class="group relative flex-1 flex items-end justify-center h-full"
                         :title="row.label + ' — ' + money(row.total) + ' (' + row.count + ' txn)'">
                        {{-- Tooltip --}}
                        <div class="pointer-events-none absolute bottom-full mb-1 left-1/2 -translate-x-1/2 z-10 hidden group-hover:block whitespace-nowrap rounded bg-slate-800 px-2 py-1 text-[11px] text-white shadow">
                            <span class="font-semibold" x-text="money(row.total)"></span>
                            <span class="text-slate-300" x-text="' · ' + row.count + ' txn'"></span>
                        </div>
                        <div class="w-full max-w-[2rem] rounded-t bg-indigo-500 group-hover:bg-indigo-600 transition-all"
                             :style="`height: ${barHeight(row.total)}%`"></div>
                    </div>
                </template>
            </div>
            <div class="flex gap-1 mt-2">
                <template x-for="(row, i) in rows" :key="'lbl-' + period + '-' + i">
                    <div class="flex-1 text-center text-[10px] leading-tight text-slate-400 truncate"
                         x-text="row.label"></div>
                </template>
            </div>
        </div>
    </template>
</div>
