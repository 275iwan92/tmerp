{{-- Teks JS layar v1 (resources/js/*.js, tanpa DevLib) ditulis ENGLISH; di sini diterjemahkan ke bahasa tampilan lewat kamus
     t() (sys_dictionary; terjemahan Indonesia di-seed migration 2026_10_02_000926_dictionary_devlib_js). JS membaca
     window.TM_T[English] -- belum ada terjemahan = English apa adanya. Teks JS baru: tambahkan ke daftar ini. --}}
@php
    $__tmJsText = [
        // app-shell.js
        'This menu is not available for your account.',
        'not available yet',
        'This screen is not built in V2 yet — display placeholder only.',
        'Number',
        'Date',
        'Remarks',
        'Enter remarks…',
        'Reference',
        'Save',
        'Cancel',
        'LIST',
        'Column A',
        'Column B',
        '(no data yet — module coming)',
        'All right, opening “:x” for you.',
        'I have not found a matching object yet. Name a form/grid, e.g. “quotation” or “inquiry”.',
        'All right, opening “:x”.',
        'No matching dashboard yet. Try e.g. “sales”, “purchasing”, “inventory”.',
        // component-library.js
        'Select...',
        ':n selected (:list)',
        'Choose both dates.',
        'Duration: the end date is not valid.',
        'Duration: :y Years :m Months',
        '(empty)',
        // dirty-guard.js
        'Save changes?',
        'This form has unsaved changes.',
        'The Save button is not available on this screen.',
        'Saving…',
        // tmdieline.js
        'This type has no<br>dieline specification yet',
        'Choose a size / enter the dimensions<br>to see the dieline',
    ];
@endphp
<script>window.TM_T = @json(array_combine($__tmJsText, array_map(fn ($s) => t($s), $__tmJsText)));</script>
