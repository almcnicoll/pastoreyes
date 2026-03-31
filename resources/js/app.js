import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import 'flowbite';
import cytoscape from 'cytoscape';
import relationshipGraph from './relationshipGraph.js';

/*
|--------------------------------------------------------------------------
| Cytoscape.js
|--------------------------------------------------------------------------
*/

window.cytoscape = cytoscape;

/*
|--------------------------------------------------------------------------
| Alpine Components
|--------------------------------------------------------------------------
|
| Register Alpine components before Livewire.start() so they are
| available when Alpine initialises any x-data attributes.
|
*/

Alpine.data('relationshipGraph', relationshipGraph);

/*
|--------------------------------------------------------------------------
| Livewire + Alpine
|--------------------------------------------------------------------------
*/

Livewire.start();
