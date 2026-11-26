{{-- PROTOTYPE (throwaway): variant switcher plus a live readout of what each variant actually did to the page. --}}
@php
    use Workbench\App\Prototype\PulseWidgetStyling\PrototypeDashboard;

    $variants = array_keys(PrototypeDashboard::VARIANTS);
    $current = PrototypeDashboard::variant();
    $grays = array_keys(PrototypeDashboard::GRAYS);
    $gray = PrototypeDashboard::grayName();
@endphp

<div
    id="prototype-switcher"
    style="position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 9999; width: min(820px, calc(100vw - 32px)); font: 12px/1.45 ui-monospace, SFMono-Regular, Menlo, monospace; color: #f9fafb; background: #111827; border: 2px solid #f472b6; border-radius: 14px; box-shadow: 0 10px 30px rgb(0 0 0 / .45); overflow: hidden;"
>
    <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #1f2937;">
        <span style="background: #f472b6; color: #111827; font-weight: 700; padding: 1px 6px; border-radius: 4px;">PROTOTYPE</span>
        <button type="button" data-step="-1" style="all: unset; cursor: pointer; padding: 0 6px; font-size: 16px;">&larr;</button>
        <strong style="flex: 1; text-align: center;">{{ $current }}: {{ PrototypeDashboard::VARIANTS[$current] }}</strong>
        <button type="button" data-step="1" style="all: unset; cursor: pointer; padding: 0 6px; font-size: 16px;">&rarr;</button>
    </div>
    <div style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; border-top: 1px solid #374151;">
        <span style="color: #9ca3af;">panel gray</span>
        @foreach ($grays as $name)
            <button type="button" data-gray="{{ $name }}" style="all: unset; cursor: pointer; padding: 1px 7px; border-radius: 5px; {{ $name === $gray ? 'background: #f472b6; color: #111827; font-weight: 700;' : 'background: #374151;' }}">{{ $name }}</button>
        @endforeach
        <span style="color: #9ca3af; margin-left: 10px;">mode</span>
        <button type="button" data-mode="light" style="all: unset; cursor: pointer; padding: 1px 7px; border-radius: 5px; background: #374151;">light</button>
        <button type="button" data-mode="dark" style="all: unset; cursor: pointer; padding: 1px 7px; border-radius: 5px; background: #374151;">dark</button>
        <a href="{{ url('/pulse') }}" target="_blank" style="margin-left: auto; color: #f9a8d4;">compare: /pulse &nearr;</a>
    </div>
    <ul id="prototype-state" style="margin: 0; padding: 6px 12px 8px 26px; border-top: 1px solid #374151; color: #d1d5db;"></ul>
</div>

<script>
    (() => {
        const variants = @json($variants);
        const current = @json($current);

        const go = (params) => {
            const url = new URL(window.location.href)
            Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value))
            window.location.assign(url)
        }

        const step = (by) => go({ variant: variants[(variants.indexOf(current) + by + variants.length) % variants.length] })

        document.querySelectorAll('#prototype-switcher [data-step]').forEach((button) => {
            button.addEventListener('click', () => step(Number(button.dataset.step)))
        })

        document.querySelectorAll('#prototype-switcher [data-gray]').forEach((button) => {
            button.addEventListener('click', () => go({ gray: button.dataset.gray }))
        })

        document.querySelectorAll('#prototype-switcher [data-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                localStorage.setItem('filament:theme:mode', button.dataset.mode)
                window.dispatchEvent(new CustomEvent('theme-changed', { detail: button.dataset.mode }))
                document.documentElement.classList.toggle('dark', button.dataset.mode === 'dark')
            })
        })

        document.addEventListener('keydown', (event) => {
            if (event.target.closest('input, textarea, select, [contenteditable]')) return
            if (event.key === 'ArrowLeft') step(-1)
            if (event.key === 'ArrowRight') step(1)
        })

        const font = (element) => element ? getComputedStyle(element).fontFamily.split(',')[0].replaceAll('"', '') : '—'

        const kb = (link) => {
            const entry = performance.getEntriesByName(link.href)[0]

            return entry?.decodedBodySize ? ` (${Math.round(entry.decodedBodySize / 1024)} KB)` : ''
        }

        const render = () => {
            const sheets = [...document.querySelectorAll('link[rel=stylesheet]')]
                .filter((link) => ! link.href.includes('fonts.bunny.net'))
                .map((link) => new URL(link.href).pathname.split('/').pop() + kb(link))

            const activity = document.querySelector('#workbench-activity')
            const missing = activity?.querySelector('.activity-css-missing')
            const serversGrid = document.querySelector('.fi-pulse-widget [class*="grid-cols-[max-content"]')
            const columns = serversGrid ? getComputedStyle(serversGrid).gridTemplateColumns.split(' ').length : 0

            const lines = [
                `stylesheets: ${sheets.join(', ')}`,
                `font: panel ${font(document.body)} · Pulse widget ${font(document.querySelector('.fi-pulse-widget section'))}`,
                `custom card css(): ${! activity ? 'card not loaded yet' : (getComputedStyle(missing).display === 'none' ? 'delivered ✓' : 'MISSING ✗')}`,
                `servers grid: ${! serversGrid ? 'not loaded yet' : (columns === 7 ? '7 columns ✓' : `${columns} column(s) ✗`)} · mode: ${document.documentElement.classList.contains('dark') ? 'dark' : 'light'} · gray: ${@json($gray)}`,
            ]

            document.getElementById('prototype-state').innerHTML = lines.map((line) => `<li>${line}</li>`).join('')
        }

        render()
        setInterval(render, 1000)
    })()
</script>
