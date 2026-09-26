@extends('layout', ['title' => $page['label'], 'dev' => true, 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <div class="dl-cat__nav">
            <x-dl::breadcrumb :items="[$page['part'] => null, $page['label'] => null]" />
            <div class="dl-cat__chips">
                @foreach ($page['members'] as $slug => [$label])
                    <x-dl::button variant="secondary" size="sm" :href="'#'.$slug" :label="$label" />
                @endforeach
            </div>
        </div>
        @foreach ($page['members'] as $slug => [$label, $classes])
            <section class="dl-cat__item" id="{{ $slug }}">
                <h2>{{ $label }} <code>{{ collect($classes)->map(fn ($c) => 'x-dl::'.\Illuminate\Support\Str::of(\Illuminate\Support\Str::after($c, 'Devlib\\Components\\'))->explode('\\')->map(fn ($p) => \Illuminate\Support\Str::kebab($p))->implode('.'))->implode(' · ') }}</code></h2>
                @include('demos.'.$slug, $data)
            </section>
        @endforeach
    </div>
    <x-dl::code-button :code="$code" :file="'resources/views/demos/*.blade.php ('.count($page['members']).' components)'" :classes="$page['classes']" :mockup-url="isset($page['mockup']) ? route('mockup', ['file' => $page['mockup']]) : null" />
@endsection
