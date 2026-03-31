<x-mail::message>
# Task Status Changed

A task status has been updated.

**Task:** {{ $task->title }}  
**Property:** {{ $task->property?->name ?? '-' }}  
**Previous Status:** {{ $oldStatus }}  
**New Status:** {{ $newStatus }}  
**Updated By:** {{ $changedByName }}

@if (!empty($workerNames))
**Assigned To:** {{ implode(', ', $workerNames) }}
@endif

<x-mail::button :url="$taskUrl">
Open Task
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>