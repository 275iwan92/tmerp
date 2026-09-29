<div>
    {{-- Header Navigation Bar --}}
    <header class="sticky top-0 z-40 bg-[#0f172a] text-white border-b border-slate-700 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="text-xl">📦</span>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-bold text-white tracking-tight">New Product Inquiry</h1>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase bg-blue-500/20 text-blue-300 border border-blue-400/40 rounded">PHP 8.4 + LIVEWIRE 4</span>
                        <span class="text-xs font-mono text-slate-400">{{ $inquiryNo }}</span>
                    </div>
                    <p class="text-xs text-slate-400">Offset Packaging SPK · Modular Multi-Part CAD & Costing</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                {{-- Status Indicator --}}
                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-800 text-slate-300 text-xs font-mono border border-slate-700">
                    <span class="w-2 h-2 rounded-full {{ $formStatus === 'CONFIRMED' ? 'bg-emerald-400 animate-pulse' : ($formStatus === 'SAVED' ? 'bg-blue-400' : 'bg-amber-400') }}"></span>
                    <span>{{ $formStatus === 'CONFIRMED' ? 'CONFIRMED' : ($formStatus === 'SAVED' ? 'SAVED · DRAFT' : 'DRAFT') }}</span>
                </div>

                <button wire:click="saveDraft" class="px-3.5 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-md transition-colors shadow-sm">
                    💾 Save Draft
                </button>
                <button wire:click="confirmInquiry" class="px-4 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 rounded-md transition-colors shadow-sm">
                    ✓ Confirm to Costing
                </button>
            </div>
        </div>

        {{-- Sales Officer Notice --}}
        <div class="bg-amber-500/10 border-t border-amber-500/20 px-4 sm:px-6 py-1.5 text-amber-200 text-xs flex items-center gap-2">
            <span>⚠️</span>
            <span><strong>Sales Technical Notice:</strong> Dus sepatu 2-piece memiliki 2 set pisau pon terpisah (Badan & Tutup). Toleransi tutup +5mm agar nyaman dipasang.</span>
        </div>
    </header>

    {{-- Toast Notification --}}
    @if($toastMessage)
        <div class="fixed bottom-5 right-5 z-50 bg-emerald-700 text-white px-4 py-3 rounded-lg shadow-xl text-xs font-bold flex items-center gap-2">
            <span>✓</span>
            <span>{{ $toastMessage }}</span>
        </div>
    @endif

    {{-- Main Container --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        {{-- 1. Inquiry Header Card --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2">
                <span>1. Commercial & Customer Header</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Customer / Klien *</label>
                    <input type="text" wire:model.live="customer" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-blue-500 focus:bg-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">Nama Produk / Draft Kemasan *</label>
                    <input type="text" wire:model.live="productNameDraft" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-blue-500 focus:bg-white">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Sales PIC Technical</label>
                    <input type="text" wire:model.live="salesPic" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Tipe Standar Kemasan</label>
                    <select wire:model.live="productType" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                        <option value="RIGID_BOX">Rigid Set-Up 2-Piece (ECMA D20)</option>
                        <option value="STE">Straight Tuck End (ECMA A20)</option>
                        <option value="RTE">Reverse Tuck End</option>
                        <option value="CRASH_LOCK">Auto-Lock Bottom</option>
                        <option value="MAILER">E-Flute Mailer Box</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Target Kirim / Deadline</label>
                    <input type="date" wire:model.live="customerNeedDate" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                </div>
                <div class="md:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">Catatan Spesifikasi Khusus</label>
                    <input type="text" wire:model.live="salesNotes" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium">
                </div>
            </div>
        </div>

        {{-- 2. Multi-Part Dieline CAD & Interactive 3D Visualizer --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <span>2. Multi-Part CAD Dielines & Standalone 3D Preview</span>
                        <span class="px-2 py-0.5 text-[10px] bg-blue-100 text-blue-700 rounded-full font-bold">2 Part Terpisah</span>
                    </h2>
                    <p class="text-xs text-slate-500">Pola bentangan pisau pon diecut untuk Badan dan Tutup dibuat dieline terpisah.</p>
                </div>

                {{-- Split View Switcher --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-600 font-medium">Mode Tampilan:</span>
                    <button type="button" wire:click="setDielineViewMode('SPLIT')" class="px-2.5 py-1 text-xs font-bold rounded {{ $dielineViewMode === 'SPLIT' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        ⚏ Side-by-Side (2 Dieline)
                    </button>
                    <button type="button" wire:click="setDielineViewMode('SINGLE')" class="px-2.5 py-1 text-xs font-bold rounded {{ $dielineViewMode === 'SINGLE' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        □ Single Active Part
                    </button>
                </div>
            </div>

            {{-- Part Selector Tabs --}}
            <div class="flex items-center gap-2">
                @foreach($parts as $p)
                    <button type="button" wire:click="selectPart('{{ $p['id'] }}')" class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-2 border transition-all {{ $activePartId === $p['id'] ? 'bg-slate-900 text-white border-slate-900 shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100' }}">
                        <span>{{ $loop->iteration }}. {{ $p['partName'] }}</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded {{ $activePartId === $p['id'] ? 'bg-blue-500 text-white' : 'bg-slate-200 text-slate-600' }}">
                            {{ $p['dimensions']['length'] }}×{{ $p['dimensions']['width'] }}×{{ $p['dimensions']['height'] }} mm
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- Dieline Render Section --}}
            @if($dielineViewMode === 'SPLIT')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach($parts as $p)
                        <div class="border {{ $activePartId === $p['id'] ? 'border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200' }} rounded-xl p-3 bg-slate-50 flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <span class="text-xs font-bold text-slate-800">{{ $loop->iteration }}. {{ $p['partName'] }}</span>
                                    <div class="text-[11px] text-slate-500 font-mono">
                                        P={{ $p['dimensions']['length'] }} mm · L={{ $p['dimensions']['width'] }} mm · T={{ $p['dimensions']['height'] }} mm
                                    </div>
                                </div>
                                <a href="{{ route('inquiry.export-dieline-svg', [
                                    'part_name' => $p['partName'],
                                    'part_type' => $p['partType'],
                                    'length' => $p['dimensions']['length'],
                                    'width' => $p['dimensions']['width'],
                                    'height' => $p['dimensions']['height'],
                                    'unit' => $unit
                                ]) }}" class="text-[11px] font-bold text-blue-600 hover:text-blue-800 bg-white border border-blue-200 px-2 py-1 rounded shadow-2xs">
                                    Unduh SVG CAD
                                </a>
                            </div>

                            <div class="w-full h-64 bg-white rounded-lg border border-slate-200 overflow-hidden flex items-center justify-center p-2">
                                {!! $splitSvgs[$p['id']] !!}
                            </div>

                            <div class="mt-2 text-[10px] text-slate-500 flex items-center justify-between">
                                <span>Garis Merah: Pisau Potong (Cut)</span>
                                <span>Garis Hijau: Rel Lipat (Crease)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-800">{{ $activePart['partName'] }}</span>
                        <a href="{{ route('inquiry.export-dieline-svg', [
                            'part_name' => $activePart['partName'],
                            'part_type' => $activePart['partType'],
                            'length' => $activePart['dimensions']['length'],
                            'width' => $activePart['dimensions']['width'],
                            'height' => $activePart['dimensions']['height'],
                            'unit' => $unit
                        ]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 bg-white border border-blue-200 px-3 py-1 rounded">
                            Unduh File SVG CAD
                        </a>
                    </div>
                    <div class="w-full h-80 bg-white rounded-lg border border-slate-200 overflow-hidden flex items-center justify-center p-4">
                        {!! $activeSvg !!}
                    </div>
                </div>
            @endif

            {{-- 3D Interactive CSS Box Viewport --}}
            <div class="mt-6 border border-slate-200 rounded-xl p-4 bg-slate-900 text-white" x-data="{ rotX: -20, rotY: 35, zoom: 1, isSpinning: false }">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-3 mb-4">
                    <div>
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span>🎮 Live 3D Box Simulator (Exploded View Rakitan Dus Sepatu)</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Lihat interaksi visual antara Badan dan Tutup yang terangkat di atasnya.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        {{-- Lid Elevation Slider --}}
                        <div class="flex items-center gap-2 bg-slate-800 px-2.5 py-1 rounded text-xs">
                            <span class="text-slate-300 text-[11px]">Elevasi Tutup:</span>
                            <input type="range" min="0" max="100" wire:model.live="lidElevation" class="w-24 accent-blue-500 cursor-pointer">
                            <span class="font-mono text-blue-300 text-[11px]">{{ $lidElevation }}px</span>
                        </div>

                        <button type="button" @click="rotX = -20; rotY = 35; zoom = 1" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-xs rounded text-slate-300">
                            Reset Sudut
                        </button>
                    </div>
                </div>

                {{-- 3D Canvas Box Container --}}
                <div class="relative w-full h-72 flex items-center justify-center overflow-hidden bg-radial from-slate-800 to-slate-950 rounded-lg select-none"
                     @mousedown="isDragging = true"
                     @mousemove="if (event.buttons === 1) { rotY += event.movementX * 0.6; rotX -= event.movementY * 0.6; }"
                     style="perspective: 1000px; cursor: grab;">
                    
                    {{-- 3D Rig --}}
                    <div class="relative transition-transform duration-75"
                         :style="`transform: scale(${zoom}) rotateX(${rotX}deg) rotateY(${rotY}deg); transform-style: preserve-3d;`">
                        
                        {{-- Part 1: Badan Sepatu (Base Tray) --}}
                        <div class="relative" style="width: 220px; height: 80px; transform-style: preserve-3d;">
                            {{-- Front --}}
                            <div class="absolute inset-0 bg-blue-700/80 border border-blue-400 flex items-center justify-center text-xs font-bold text-white shadow-inner"
                                 style="transform: translateZ(70px);">Badan Depan</div>
                            {{-- Back --}}
                            <div class="absolute inset-0 bg-blue-800/80 border border-blue-500 flex items-center justify-center text-xs font-bold text-slate-300"
                                 style="transform: rotateY(180deg) translateZ(70px);">Badan Belakang</div>
                            {{-- Left --}}
                            <div class="absolute inset-y-0 bg-blue-900/80 border border-blue-500 flex items-center justify-center text-xs font-bold text-slate-300"
                                 style="width: 140px; left: -70px; transform: rotateY(-90deg) translateZ(0px);">Kiri</div>
                            {{-- Right --}}
                            <div class="absolute inset-y-0 bg-blue-900/80 border border-blue-500 flex items-center justify-center text-xs font-bold text-slate-300"
                                 style="width: 140px; right: -70px; transform: rotateY(90deg) translateZ(0px);">Kanan</div>
                            {{-- Bottom Floor --}}
                            <div class="absolute bg-blue-950/90 border border-blue-600 flex items-center justify-center text-xs font-bold text-slate-400"
                                 style="width: 220px; height: 140px; top: -30px; transform: rotateX(-90deg) translateZ(40px);">Alas Sepatu (Floor)</div>
                        </div>

                        {{-- Part 2: Tutup Sepatu (Lid Cover) Elevated --}}
                        <div class="absolute"
                             style="width: 226px; height: 30px; top: -35px; left: -3px; transform-style: preserve-3d; transform: translateY(-{{ $lidElevation }}px);">
                            {{-- Lid Top Surface --}}
                            <div class="absolute bg-sky-500/90 border-2 border-sky-300 flex items-center justify-center text-xs font-bold text-white shadow-lg"
                                 style="width: 226px; height: 146px; top: -58px; left: 0px; transform: rotateX(90deg) translateZ(15px);">
                                TUTUP SEPATU (LID)
                            </div>
                            {{-- Lid Front Flap --}}
                            <div class="absolute inset-0 bg-sky-600/90 border border-sky-300 flex items-center justify-center text-[10px] font-bold text-white"
                                 style="transform: translateZ(73px);">Bibir Depan</div>
                            {{-- Lid Back Flap --}}
                            <div class="absolute inset-0 bg-sky-700/90 border border-sky-400 flex items-center justify-center text-[10px] font-bold text-white"
                                 style="transform: rotateY(180deg) translateZ(73px);">Bibir Belakang</div>
                        </div>
                    </div>
                </div>
                <div class="mt-2 text-center text-[11px] text-slate-400">
                    💡 Geser mouse untuk memutar sudut pandang 3D secara bebas 360°
                </div>
            </div>
        </div>

        {{-- 3. Tiered Order Quantities --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">3. Tiered Order Quantities</h2>
                <button type="button" wire:click="addTier" class="px-3 py-1 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md border border-blue-200">
                    + Tambah Tier Qty
                </button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                @foreach($tiers as $tier)
                    <div class="p-3 rounded-lg border {{ $tier['isPrimary'] ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-200 bg-slate-50' }} flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-700">Tier #{{ $tier['tierNumber'] }}</span>
                            @if($tier['isPrimary'])
                                <span class="text-[10px] bg-emerald-600 text-white font-bold px-1.5 py-0.2 rounded">Primary</span>
                            @endif
                        </div>
                        <div class="my-2">
                            <span class="text-base font-extrabold text-slate-900 font-mono">{{ number_format($tier['qty']) }}</span>
                            <span class="text-slate-500 text-[11px]"> pcs</span>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                            <button type="button" wire:click="setPrimaryTier('{{ $tier['id'] }}')" class="text-[11px] text-blue-600 hover:underline">Set Primary</button>
                            <button type="button" wire:click="deleteTier('{{ $tier['id'] }}')" class="text-[11px] text-red-500 hover:text-red-700">Hapus</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 4. Tooling & External Costs Tables --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3">4. Tooling & Pisau Pon</h2>
                <div class="divide-y divide-slate-100 text-xs">
                    @foreach($toolings as $tl)
                        <div class="py-2.5 flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-slate-800">{{ $tl['toolingType'] }}</div>
                                <div class="text-[11px] text-slate-500">{{ $tl['notes'] }}</div>
                            </div>
                            <span class="font-mono font-bold text-slate-800">Rp {{ number_format($tl['cost']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3">5. Biaya Eksternal & Ekspedisi</h2>
                <div class="divide-y divide-slate-100 text-xs">
                    @foreach($externalCosts as $ec)
                        <div class="py-2.5 flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-slate-800">{{ $ec['description'] }}</div>
                                <div class="text-[11px] text-slate-500">{{ $ec['taxTreatment'] }}</div>
                            </div>
                            <span class="font-mono font-bold text-slate-800">Rp {{ number_format($ec['amount']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </main>
</div>
