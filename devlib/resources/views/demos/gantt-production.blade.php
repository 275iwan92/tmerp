<x-dl::gantt :unscheduled="2" :start="6" :end="54" :px-per-hour="46" origin-date="{{ \App\Support\GanttDemo::ORIGIN }}"
    :lock-until="\App\Support\GanttDemo::LOCK_UNTIL" :now-at="\App\Support\GanttDemo::NOW"
    impact-url="{{ url('/demo/gantt/impact') }}" move-url="{{ url('/demo/gantt/move') }}" draft-label="DRAFT 0 change"
    :submit-label="null" resource-label="Machine"
    :categories="\App\Support\GanttDemo::categories()" :rows="\App\Support\GanttDemo::rows()" :tasks="\App\Support\GanttDemo::tasks()" />
