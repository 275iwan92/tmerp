@extends('layout', ['title' => $page['label'], 'dev' => true, 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <div class="dl-cat__nav">
            {{-- menu: Modul / folder di breadcrumb bisa diklik = buka Layer Menu di cabang itu (user 2026-09-30). --}}
            {{-- Nama halaman juga bisa diklik (kembali ke keadaan awal halaman, mis. keluar dari + New Record). --}}
            <x-dl::breadcrumb menu :items="array_fill_keys($page['trail'] ?? [$page['part']], null) + [$page['label'] => url()->current()]" :current="request()->boolean('new') ? 'New Record' : null" />
            {{-- Tombol lompat hanya berguna bila halaman berisi >1 contoh. --}}
            @if (count($page['members']) > 1)
                <div class="dl-cat__chips">
                    @foreach ($page['members'] as $slug => [$label])
                        <x-dl::button variant="secondary" size="sm" :href="'#'.$slug" :label="$label" />
                    @endforeach
                </div>
            @endif
        </div>
        @foreach ($page['members'] as $slug => $member)
            @php
                [$label, $classes] = $member;
                $mode = ($page['modes'] ?? false) ? ($member[2] ?? 'wide') : null;
                $objects = collect($classes)->map(fn ($c) => [\Devlib\Components\DevlibComponent::describe($c)[0], $c]);
            @endphp
            <section class="dl-cat__item" id="{{ $slug }}">
                @if ($mode === null)
                    <h2>{{ $label }} <code>{{ $objects->pluck(0)->implode(' · ') }}</code></h2>
                    @include('demos.'.$slug, $data)
                @else
                    {{-- Kelompok per jenis data (user 2026-10-05): nama object + layar dibelah ReadWrite | ReadOnly. --}}
                    <div class="dl-cat__head">
                        <h2>{{ $label }}</h2>
                        @foreach ($objects as [$tag, $class])
                            <span class="dl-cat__obj"><code>{{ $tag }}</code><small>{{ $class }}</small></span>
                        @endforeach
                    </div>
                    @if ($mode === 'wide')
                        <div class="dl-cat__mode dl-cat__mode--wide"><b>Full width</b></div>
                        @include('demos.'.$slug, $data)
                    @else
                        <div class="dl-cat__modes">
                            <div class="dl-cat__side">
                                <div class="dl-cat__mode dl-cat__mode--rw"><b>ReadWrite</b> input</div>
                                @if ($mode === 'ro')
                                    <div class="dl-cat__none">No ReadWrite mode</div>
                                @else
                                    @include('demos.'.$slug, $data)
                                @endif
                            </div>
                            {{-- form sendiri: radio bernama sama di dua sisi tidak saling merebut pilihan --}}
                            <form class="dl-cat__side" onsubmit="return false">
                                <div class="dl-cat__mode dl-cat__mode--ro"><b>ReadOnly</b> display</div>
                                @if ($mode === 'rw')
                                    <div class="dl-cat__none">No ReadOnly mode</div>
                                @elseif ($mode === 'ro')
                                    @include('demos.'.$slug, $data)
                                @else
                                    @php(\Devlib\Components\Field\Field::$readonlyAll = true)
                                    @include('demos.'.$slug, $data)
                                    @php(\Devlib\Components\Field\Field::$readonlyAll = false)
                                @endif
                            </form>
                        </div>
                    @endif
                @endif
            </section>
        @endforeach
    </div>
    <x-dl::code-button :code="$code" :file="'resources/views/demos/*.blade.php ('.count($page['members']).' components)'" :classes="$page['classes']" :mockup-url="isset($page['mockup']) ? route('mockup', ['file' => $page['mockup']]) : null" />
    @php($qc = \App\Support\Qc::bar((string) request()->route('slug'), $page['label'], session('devlib_user')))
    <x-dl::qc-bar :key="$qc['key']" :name="$qc['name']" :stage="$qc['stage']" :status="$qc['status']" :note="$qc['note']" :by="$qc['by']" :at="$qc['at']" :action="$qc['action']" :csrf="$qc['csrf']" :can-review="$qc['canReview']" :chain="$qc['chain']" :release="$qc['release']" />
@endsection
