{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- drag&drop
    upload, forward file yg di-drop ke <input type=file> ASLI (bukan hidden
    text spt komponen lain -- ini butuh native Livewire file upload,
    WithFileUploads di komponen pemanggil, wire:model apa adanya, sama pola
    dgn setup/identification.blade.php::$logo).
--}}
@props(['label' => null, 'name' => null, 'hint' => null, 'accept' => null, 'currentName' => null])
<div class="flex flex-col gap-1" x-data="tmFileDropzone()">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <div @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop($event)"
        @click="$refs.input.click()"
        :class="dragOver ? 'border-accent bg-[#eff6ff]' : 'border-[#c7cfda] bg-[#fafafa]'"
        class="border-2 border-dashed rounded-md px-3 py-2.5 text-center cursor-pointer transition-colors">
        <input type="file" x-ref="input" @change="onPicked($event)" @if ($accept) accept="{{ $accept }}" @endif
            {{ $attributes->merge(['class' => 'hidden']) }}>
        <template x-if="!fileName">
            <div class="flex items-center justify-center gap-2 text-muted text-xs">
                <span>📁</span><span>Drag file / <strong class="text-accent">{{ \App\Support\Localization\Translator::t('shared_file_dropzone_action', 'Pilih File') }}</strong></span>
            </div>
        </template>
        <template x-if="fileName">
            <div class="flex items-center justify-center gap-2 text-xs font-semibold text-[#374151]" x-text="'📄 ' + fileName"></div>
        </template>
    </div>
    @if ($currentName)<div class="text-[10.5px] text-muted">{{ \App\Support\Localization\Translator::t('shared_file_dropzone_current', 'Saat ini:') }} {{ $currentName }}</div>@endif
    @if ($hint)<div class="text-[10.5px] text-muted">{{ $hint }}</div>@endif
</div>
