@extends('layout', ['title' => $page['label'], 'dev' => true, 'bodyClass' => $page['full'] ? '' : 'dl-page-body'])
@section('body')
    @include('demos.'.$demo, $data)
    <x-dl::code-button :code="$code" :file="'resources/views/demos/'.$demo.'.blade.php'" :classes="$page['classes']" :mockup-url="isset($page['mockup']) ? route('mockup', ['file' => $page['mockup']]) : null" />
    @php($qc = \App\Support\Qc::bar((string) request()->route('slug'), $page['label'], session('devlib_user')))
    <x-dl::qc-bar :key="$qc['key']" :name="$qc['name']" :stage="$qc['stage']" :status="$qc['status']" :note="$qc['note']" :by="$qc['by']" :at="$qc['at']" :action="$qc['action']" :csrf="$qc['csrf']" :can-review="$qc['canReview']" :chain="$qc['chain']" :release="$qc['release']" />
@endsection
