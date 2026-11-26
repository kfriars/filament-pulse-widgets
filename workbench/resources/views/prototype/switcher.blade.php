{{-- PROTOTYPE (throwaway): the variant switcher and a live log of every Livewire request on the page. --}}
@php
    use Workbench\App\Prototype\PulseWidgetShape\PrototypeDashboard;

    $variants = array_keys(PrototypeDashboard::VARIANTS);
    $current = in_array(request()->query('variant'), $variants, true) ? request()->query('variant') : $variants[0];
    $denied = (bool) session('prototype.deny-pulse');
@endphp

<div
    id="prototype-switcher"
    style="position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 9999; width: min(760px, calc(100vw - 32px)); font: 12px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace; color: #f9fafb; background: #111827; border: 2px solid #f472b6; border-radius: 14px; box-shadow: 0 10px 30px rgb(0 0 0 / .45); overflow: hidden;"
>
    <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #1f2937;">
        <span style="background: #f472b6; color: #111827; font-weight: 700; padding: 1px 6px; border-radius: 4px;">PROTOTYPE</span>
        <button type="button" data-step="-1" style="all: unset; cursor: pointer; padding: 0 6px; font-size: 16px;">&larr;</button>
        <strong style="flex: 1; text-align: center;">{{ $current }}: {{ PrototypeDashboard::VARIANTS[$current] }}</strong>
        <button type="button" data-step="1" style="all: unset; cursor: pointer; padding: 0 6px; font-size: 16px;">&rarr;</button>
        <button
            type="button"
            id="prototype-gate"
            style="all: unset; cursor: pointer; padding: 2px 8px; border-radius: 6px; background: {{ $denied ? '#b91c1c' : '#047857' }};"
            title="Flip the viewPulse gate for this session without reloading, then watch the next requests"
        >viewPulse: {{ $denied ? 'DENIED' : 'allowed' }}</button>
    </div>
    <ol id="prototype-log" style="margin: 0; padding: 6px 12px 8px 32px; max-height: 132px; overflow-y: auto;"></ol>
</div>

<script>
    (() => {
        const variants = @json($variants);
        const current = @json($current);

        const go = (step) => {
            const next = variants[(variants.indexOf(current) + step + variants.length) % variants.length]
            const url = new URL(window.location.href)
            url.searchParams.set('variant', next)
            window.location.assign(url)
        }

        document.querySelectorAll('#prototype-switcher [data-step]').forEach((button) => {
            button.addEventListener('click', () => go(Number(button.dataset.step)))
        })

        document.addEventListener('keydown', (event) => {
            if (event.target.closest('input, textarea, select, [contenteditable]')) return
            if (event.key === 'ArrowLeft') go(-1)
            if (event.key === 'ArrowRight') go(1)
        })

        document.getElementById('prototype-gate').addEventListener('click', async (event) => {
            const response = await fetch(@json(url('/prototype/toggle-pulse-gate')), { headers: { Accept: 'application/json' } });
            const { denied } = await response.json()
            event.target.textContent = `viewPulse: ${denied ? 'DENIED' : 'allowed'}`
            event.target.style.background = denied ? '#b91c1c' : '#047857'
        })

        const log = document.getElementById('prototype-log')

        const describe = (component) => {
            const name = JSON.parse(component.snapshot).memo.name
            const calls = (component.calls ?? []).map((call) => call.method)
            const updates = Object.keys(component.updates ?? {})

            if (calls.length) return `${name} → ${calls.join(', ')}`
            if (updates.length) return `${name} → set ${updates.join(', ')}`

            return `${name} → re-render (poll / reactive prop)`
        }

        const watchRequests = () => {
            Livewire.hook('request', ({ payload, respond }) => {
                let lines = []

                try {
                    lines = JSON.parse(payload).components.map(describe)
                } catch (error) {
                    lines = ['(unreadable payload)']
                }

                const time = new Date().toLocaleTimeString()

                respond(({ status }) => {
                    const item = document.createElement('li')
                    item.style.color = status === 200 ? '#d1d5db' : '#fca5a5'
                    item.textContent = `${time}  ${status}  ${lines.join('  |  ')}`
                    log.prepend(item)

                    while (log.children.length > 40) log.lastChild.remove()
                })
            })
        }

        window.Livewire ? watchRequests() : document.addEventListener('livewire:init', watchRequests)
    })()
</script>
