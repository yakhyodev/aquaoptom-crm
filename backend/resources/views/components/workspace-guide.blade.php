@props(['icon' => '◈', 'title', 'description', 'steps' => []])
<section class="workspace-guide">
    <div class="workspace-guide-title"><span class="workspace-guide-icon" aria-hidden="true">{{ $icon }}</span><div><h1>{{ $title }}</h1><p>{{ $description }}</p></div></div>
    @if(count($steps))<ol class="workspace-steps">@foreach($steps as $step)<li><b>{{ $loop->iteration }}</b><span>{{ $step }}</span></li>@endforeach</ol>@endif
</section>
