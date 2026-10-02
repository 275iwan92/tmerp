{{-- Papan widget saja (tanpa salam): jawaban aksi Customize / muat ulang sesudah perintah asisten. Prop-nya harus sama dengan
     yang dipakai x-dl::dashboard saat halaman dibuka. Isi = App\Support\V2\Board\Board::html(). --}}
<x-dl::widget-board :widgets="$board['widgets']" :catalog="$board['catalog']" :action-url="$board['board_url']" :title="$board['kpi_title']"
    :id="'dash-'.$board['profile'].'-board'" :source="$board['board_source']" :periods="$board['board_periods']" :period="$board['board_period']" :dashboards="$board['board_dashboards']" :dashboard="$board['board_dashboard']"
    :editable="$board['board_editable']" />
