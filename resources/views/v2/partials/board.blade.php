{{--
 REMARK
 Tujuan       : Merender papan widget saja (tanpa salam) sebagai jawaban aksi Customize atau muat ulang.
 Sumber hulu  : App\Support\V2\Board\Board::board() dan Board::html().
 Input        : $board (widgets, catalog, board_url, kpi_title, profile, periode, dashboard, editable).
 Output       : x-dl::widget-board dengan prop yang sama seperti x-dl::dashboard.
 Dipakai oleh : App\Support\V2\Board\Board::html() (view v2.partials.board).
 Status       : Aktif.
--}}
<x-dl::widget-board :widgets="$board['widgets']" :catalog="$board['catalog']" :action-url="$board['board_url']" :title="$board['kpi_title']"
    :id="'dash-'.$board['profile'].'-board'" :source="$board['board_source']" :periods="$board['board_periods']" :period="$board['board_period']" :dashboards="$board['board_dashboards']" :dashboard="$board['board_dashboard']"
    :editable="$board['board_editable']" />
