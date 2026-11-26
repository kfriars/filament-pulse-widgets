# Filament Pulse Widgets

Puts Laravel Pulse's monitoring data on Filament v5 panels, alongside the panel's own widgets.

## Language

**Pulse card**:
A Livewire component from Laravel Pulse that shows one kind of monitoring data, either first-party (Servers, Usage, Slow Queries, …) or custom-built.
_Avoid_: Card (on its own), Pulse widget

**Widget**:
A Filament dashboard widget: a component a Filament panel places on a dashboard or page.
_Avoid_: Card

**Pulse widget**:
A widget that contains one Pulse card, unchanged. The package ships one for each first-party Pulse card, and a developer can make one for a custom Pulse card.
_Avoid_: Card, Pulse card widget

**Placement**:
One use of a Pulse widget on a page, with its own options. A page can hold several placements of the same Pulse widget.
_Avoid_: Instance

**Pulse page**:
A Filament page, inside the panel, that lays out Pulse widgets the way Pulse's own dashboard lays out Pulse cards.
_Avoid_: Pulse dashboard

**Pulse dashboard**:
Pulse's own standalone page (served at `/pulse` by default), outside any Filament panel.
_Avoid_: Pulse page

**Period**:
The stretch of recent time a Pulse card shows data for: the past hour, 6 hours, 24 hours or 7 days.
_Avoid_: Date range, timeframe
