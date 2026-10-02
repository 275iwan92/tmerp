<div class="dl-demo" style="max-width:980px">
    {{-- nodes: [id, parent (0 = top), level, name, code piece, active]. The code = pieces joined from level 1 down. --}}
    <x-dl::code-chain name="category" code-label="Item Code" :value="9" add-url="#new={level}&parent={parent}"
        :levels="['Category 1', 'Category 2', 'Category 3', 'Category 4', 'Category 5']"
        :nodes="[
            [1, 0, 1, 'Paper', 'P', 1], [2, 0, 1, 'Ink', 'I', 1], [3, 0, 1, 'Sheet', 'S', 1],
            [4, 1, 2, 'Art carton', 'C', 1], [5, 1, 2, 'Duplex', 'D', 1], [6, 1, 2, 'Ivory', 'Y', 1],
            [7, 4, 3, '210gr', 'C', 1], [8, 4, 3, '230gr', 'D', 1],
            [9, 7, 4, '65 X 100 CM', '02', 1], [10, 7, 4, '79 X 109 CM', '03', 1],
            [11, 9, 5, 'GOLDEN', '002', 1], [12, 10, 5, 'GOLDEN', '003', 1],
            [13, 5, 3, '250gr', 'A', 1], [14, 13, 4, '105 X 102 CM', '01', 1], [15, 14, 5, 'DAYASA', '001', 1], [16, 14, 5, 'IKA DP', '012', 1],
            [17, 2, 2, 'OFFSET', 'O', 1], [18, 17, 3, 'HN', 'H', 1], [19, 18, 4, 'AMBRI', '3', 1], [20, 19, 5, 'AOK RED SAR', '1', 1], [21, 19, 5, 'BLACK SAR', '2', 1],
            [22, 3, 2, 'SHBF', 'B', 1], [23, 22, 3, 'K150/M150/K200', '3', 1], [24, 23, 4, '505 X 700', '2', 1],
        ]" />
</div>
