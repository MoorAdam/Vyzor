import "./bootstrap";

import {
    Livewire,
    Alpine,
} from "../../vendor/livewire/livewire/dist/livewire.esm";
import rover from "@sheaf/rover";

window.Alpine = Alpine;

Alpine.plugin(rover);

await import("./components/select");

Livewire.start();
