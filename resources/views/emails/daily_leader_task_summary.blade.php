<x-mail::message>
# Daily Team Task Summary

Hello **{{ $recipientName }}**,

Here is the team task summary for **{{ $scheduleDate }}**.

@if (empty($summaryRows))
There are no active employee or intern tasks for today.
@else
@foreach ($summaryRows as $row)
## {{ $row['employee_name'] }} — {{ $row['role'] }}

@if (!empty($row['today_tasks']))
**Today's Tasks**

<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; margin-bottom: 16px;">
    <thead>
        <tr>
            <th align="left" style="border-bottom:1px solid #ddd;">Task</th>
            <th align="left" style="border-bottom:1px solid #ddd;">Property</th>
            <th align="left" style="border-bottom:1px solid #ddd;">Milestone</th>
            <th align="left" style="border-bottom:1px solid #ddd;">Time</th>
            <th align="left" style="border-bottom:1px solid #ddd;">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($row['today_tasks'] as $task)
        <tr>
            <td style="border-bottom:1px solid #eee;">{{ $task['title'] }}</td>
            <td style="border-bottom:1px solid #eee;">{{ $task['property'] }}</td>
            <td style="border-bottom:1px solid #eee;">{{ $task['milestone'] }}</td>
            <td style="border-bottom:1px solid #eee;">{{ $task['time'] }}</td>
            <td style="border-bottom:1px solid #eee;">{{ $task['status'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if (!empty($row['overdue_tasks']))
**Overdue Tasks**

<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; margin-bottom: 20px;">
    <thead>
        <tr>
            <th align="left" style="border-bottom:1px solid #ddd;">Task</th>
            <th align="left" style="border-bottom:1px solid #ddd;">Property</th>
            <th align="left" style="border-bottom:1px solid #ddd;">Milestone</th>
            <th align="left" style="border-bottom:1px solid #ddd;">Time</th>
            <th align="left" style="border-bottom:1px solid #ddd;">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($row['overdue_tasks'] as $task)
        <tr>
            <td style="border-bottom:1px solid #eee;">{{ $task['title'] }}</td>
            <td style="border-bottom:1px solid #eee;">{{ $task['property'] }}</td>
            <td style="border-bottom:1px solid #eee;">{{ $task['milestone'] }}</td>
            <td style="border-bottom:1px solid #eee;">{{ $task['time'] }}</td>
            <td style="border-bottom:1px solid #eee;">{{ $task['status'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
@endforeach
@endif


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>