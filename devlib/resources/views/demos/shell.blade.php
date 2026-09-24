{{-- Menu utama sesuai pilihan user: Layer Menu (bawaan, x-dl::layer-shell) atau Menu Classic (x-dl::shell). --}}
<x-dynamic-component :component="\Devlib\Components\Shell::preferred()" :modules="$modules" :user="$user" :logout-action="route('logout')" :chat="\App\Support\DemoData::chat()" developer />
