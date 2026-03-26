import './bootstrap';

import Alpine from 'alpinejs';
import { Livewire, Alpine as LivewireAlpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import 'flowbite';
import cytoscape from 'cytoscape';

/*
|--------------------------------------------------------------------------
| Alpine.js
|--------------------------------------------------------------------------
*/

window.Alpine = Alpine;

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
| Livewire + Alpine Integration
|--------------------------------------------------------------------------
*/

Livewire.start();
