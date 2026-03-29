import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import 'flowbite';
import cytoscape from 'cytoscape';

/*
|--------------------------------------------------------------------------
| Cytoscape.js
|--------------------------------------------------------------------------
|
| Make Cytoscape available globally so Blade/Livewire components
| can initialise graph instances without additional imports.
|
*/

window.cytoscape = cytoscape;

/*
|--------------------------------------------------------------------------
| Livewire + Alpine
|--------------------------------------------------------------------------
|
| Livewire v3 bundles Alpine internally. We use that version only —
| do NOT import alpinejs separately as it causes conflicts.
|
*/

Livewire.start();
