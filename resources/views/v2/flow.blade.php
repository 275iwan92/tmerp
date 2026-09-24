@extends('v2.layout', ['title' => $flow['title'], 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::flow :title="$flow['title']" :steps="$flow['steps']" source="app/Support/Flows.php" />
        <p class="dl-note">Arahkan mouse ke blok untuk melihat aturan singkatnya; klik blok untuk membuka layarnya. Status blok = status mockup layar itu.</p>
    </div>
@endsection
