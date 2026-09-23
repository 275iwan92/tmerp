<div class="dl-demo">
    <x-dl::field.rating name="rating" label="Rating" :value="3" />
    <x-dl::field.rating name="kualitas" label="Setengah bintang (klik sisi kiri bintang)" :value="3.5" half />
    <x-dl::field.rating name="ulasan" label="Baca saja, besar" :value="4.5" half readonly size="lg" />
    <x-dl::field.rating name="skor" label="Skala 10" :max="10" :value="7" />
</div>
