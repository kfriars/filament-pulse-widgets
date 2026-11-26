{{-- PROTOTYPE (throwaway): variant switcher plus a live readout of what each Pulse card is doing. --}}
@php
    use Workbench\App\Prototype\PulseWidgetPeriodSelector\PrototypeDashboard;

    $variants = array_keys(PrototypeDashboard::VARIANTS);
    $current = PrototypeDashboard::variant();
@endphp

<div
    id="prototype-switcher"
    style="position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 9999; width: min(860px, calc(100vw - 32px)); font: 12px/1.45 ui-monospace, SFMono-Regular, Menlo, monospace; color: #f9fafb; background: #111827; border: 2px solid #f472b6; border-radius: 14px; box-shadow: 0 10px 30px rgb(0 0 0 / .45); overflow: hidden;"
>
    <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #1f2937;">
        <span style="background: #f472b6; color: #111827; font-weight: 700; padding: 1px 6px; border-radius: 4px;">PROTOTYPE</span>
        <button type="button" data-step="-1" style="all: unset; cursor: pointer; padding: 0 6px; font-size: 16px;">&larr;</button>
        <strong style="flex: 1; text-align: center;">{{ $current }}: {{ PrototypeDashboard::VARIANTS[$current] }}</strong>
        <button type="button" data-step="1" style="all: unset; cursor: pointer; padding: 0 6px; font-size: 16px;">&rarr;</button>
        <button type="button" id="prototype-collapse" style="all: unset; cursor: pointer; padding: 0 6px; color: #9ca3af;">hide</button>
    </div>
    <div id="prototype-body">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 6px 12px; border-top: 1px solid #374151;">
            <span style="color: #9ca3af;">columns</span>
            @foreach (PrototypeDashboard::COLUMNS as $columns)
                <button type="button" data-param="columns" data-value="{{ $columns }}" style="all: unset; cursor: pointer; padding: 1px 7px; border-radius: 5px; {{ $columns === PrototypeDashboard::columns() ? 'background: #f472b6; color: #111827; font-weight: 700;' : 'background: #374151;' }}">{{ $columns }}</button>
            @endforeach
            <span style="color: #9ca3af; margin-left: 10px;">locale</span>
            @foreach (PrototypeDashboard::LOCALES as $locale)
                <button type="button" data-param="locale" data-value="{{ $locale }}" style="all: unset; cursor: pointer; padding: 1px 7px; border-radius: 5px; {{ $locale === PrototypeDashboard::locale() ? 'background: #f472b6; color: #111827; font-weight: 700;' : 'background: #374151;' }}">{{ $locale }}</button>
            @endforeach
            <span style="color: #9ca3af; margin-left: 10px;">mode</span>
            <button type="button" data-mode="light" style="all: unset; cursor: pointer; padding: 1px 7px; border-radius: 5px; background: #374151;">light</button>
            <button type="button" data-mode="dark" style="all: unset; cursor: pointer; padding: 1px 7px; border-radius: 5px; background: #374151;">dark</button>
            <a href="{{ url('/pulse') }}" target="_blank" style="margin-left: auto; color: #f9a8d4;">compare: /pulse &nearr;</a>
        </div>
        <ul id="prototype-state" style="margin: 0; padding: 6px 12px 8px 26px; border-top: 1px solid #374151; color: #d1d5db;"></ul>
    </div>
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

        document.querySelectorAll('#prototype-switcher [data-param]').forEach((button) => {
            button.addEventListener('click', () => go({ [button.dataset.param]: button.dataset.value }))
        })

        document.querySelectorAll('#prototype-switcher [data-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                localStorage.setItem('filament:theme:mode', button.dataset.mode)
                window.dispatchEvent(new CustomEvent('theme-changed', { detail: button.dataset.mode }))
                document.documentElement.classList.toggle('dark', button.dataset.mode === 'dark')
            })
        })

        const body = document.getElementById('prototype-body')
        document.getElementById('prototype-collapse').addEventListener('click', (event) => {
            body.hidden = ! body.hidden
            event.target.textContent = body.hidden ? 'show' : 'hide'
        })

        document.addEventListener('keydown', (event) => {
            if (event.target.closest('input, textarea, select, [contenteditable]')) return
            if (event.key === 'ArrowLeft') step(-1)
            if (event.key === 'ArrowRight') step(1)
        })

        // Every request a Pulse card makes on its own (5s polls, sort/type changes), counted per card name.
        const requests = {}
        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ component }) => {
                requests[component.name] = (requests[component.name] ?? 0) + 1
            })
        })

        const isCard = (component) => component.name.startsWith('pulse.') || component.name === 'workbench.activity'

        const render = () => {
            if (! window.Livewire) return

            const cards = Livewire.all().filter(isCard).map((component) => {
                const selector = component.el.querySelector('[data-period-selector]')
                const header = component.el.querySelector('header')
                const truncated = selector?.closest('.truncate')
                const clipped = truncated && truncated.scrollWidth > truncated.clientWidth

                return `<b>${component.name}</b> · period ${component.snapshot.data.period}`
                    + ` · ${! header ? 'no card header → no selector' : (selector ? `selector ✓${clipped ? ' <span style="color:#fca5a5">(details truncated: control may be cut off ✗)</span>' : ''}` : 'selector MISSING ✗')}`
                    + ` · own requests ${requests[component.name] ?? 0}`
            })

            const url = new URL(window.location.href)

            const lines = [
                ...cards,
                `address bar: ${url.searchParams.has('period') ? '<span style="color:#fca5a5">?period= written ✗</span>' : 'no ?period= ✓'}`
                    + ` · locale ${document.documentElement.lang} · mode ${document.documentElement.classList.contains('dark') ? 'dark' : 'light'}`,
            ]

            document.getElementById('prototype-state').innerHTML = lines.map((line) => `<li>${line}</li>`).join('')
        }

        render()
        setInterval(render, 1000)
    })()
</script>
