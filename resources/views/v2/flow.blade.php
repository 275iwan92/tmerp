@extends('v2.layout', ['title' => $flow['title'], 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::flow :title="$flow['title']" :steps="$flow['steps']" source="app/Support/Flows.php" />
        <p class="dl-note">{{ t('Hover over a block to see its short rule; click a block to open its screen. Block status = the mockup status of that screen.') }}</p>
    </div>
@endsection
