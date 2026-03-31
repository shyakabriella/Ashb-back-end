<x-mail::message>
# Daily Task Reminder

Hello **{{ $workerName }}**,

This is your task reminder for **{{ $scheduleDate }}**.

@if (!empty($todayTasks))
## Your Tasks for Today

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
        @foreach ($todayTasks as $task)
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

@if (!empty($overdueTasks))
## Overdue Warning

The following tasks are overdue and still not completed. Please update or complete them as soon as possible.

<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; margin-bottom: 18px;">
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
        @foreach ($overdueTasks as $task)
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

Completed tasks are not included in reminder emails.

<x-mail::button :url="$dashboardUrl">
Open My Tasks
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>