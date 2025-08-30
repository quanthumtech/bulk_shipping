@php
    $service = $data['service'];
    $source = $data['source'];
    $embed = $data['embed'];
    $width = $data['width'] ?? 580;
    $height = $data['height'] ?? 320;
    $caption = $data['caption'] ?? '';

    // Helper function to get service-specific class
    $getServiceClass = function($baseService) use ($service) {
        return "editorjs-embed__content--$baseService";
    };
@endphp

<div class="editorjs-embed">
    <div class="editorjs-embed__content {{ $getServiceClass($service) }}">
        @switch($service)
            @case('youtube')
                <iframe
                    class="editorjs-embed__iframe"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    src="{{ $embed }}"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                ></iframe>
                @break

            @case('facebook')
                <div class="editorjs-embed__facebook">
                    <iframe
                        class="editorjs-embed__iframe"
                        src="https://www.facebook.com/plugins/post.php?href={{ urlencode($source) }}&width={{ $width }}"
                        width="{{ $width }}"
                        height="{{ $height }}"
                        scrolling="no"
                        frameborder="0"
                        allowTransparency="true"
                        allow="encrypted-media"
                    ></iframe>
                </div>
                @break

            @case('instagram')
                <div class="editorjs-embed__instagram">
                    <iframe
                        class="editorjs-embed__iframe"
                        src="{{ $embed }}"
                        width="{{ $width }}"
                        height="{{ $height }}"
                        frameborder="0"
                        scrolling="no"
                        allowtransparency="true"
                    ></iframe>
                </div>
                @break

            @case('twitter')
                <div class="editorjs-embed__twitter">
                    <blockquote class="twitter-tweet">
                        <a href="{{ $source }}"></a>
                    </blockquote>
                    <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
                </div>
                @break

            @case('twitch-video')
                <iframe
                    class="editorjs-embed__iframe"
                    src="https://player.twitch.tv/?video={{ $embed }}&parent={{ request()->getHost() }}"
                    height="{{ $height }}"
                    width="{{ $width }}"
                    allowfullscreen
                ></iframe>
                @break

            @case('twitch-channel')
                <iframe
                    class="editorjs-embed__iframe"
                    src="https://player.twitch.tv/?channel={{ $embed }}&parent={{ request()->getHost() }}"
                    height="{{ $height }}"
                    width="{{ $width }}"
                    allowfullscreen
                ></iframe>
                @break

            @case('miro')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                    scrolling="no"
                    allowfullscreen
                ></iframe>
                @break

            @case('vimeo')
                <iframe
                    class="editorjs-embed__iframe"
                    src="https://player.vimeo.com/video/{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                ></iframe>
                @break

            @case('gfycat')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                    scrolling="no"
                    allowfullscreen
                ></iframe>
                @break

            @case('imgur')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}/embed"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                    class="imgur-embed-iframe-pub"
                    allowfullscreen
                ></iframe>
                @break

            @case('vine')
                <iframe
                    class="editorjs-embed__iframe editorjs-embed__iframe--vine"
                    src="{{ $embed }}/embed/simple"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                ></iframe>
                @break

            @case('aparat')
                <div class="editorjs-embed__aparat">
                    <iframe
                        class="editorjs-embed__iframe"
                        src="{{ $embed }}"
                        width="{{ $width }}"
                        height="{{ $height }}"
                        frameborder="0"
                        allowfullscreen
                    ></iframe>
                </div>
                @break

            @case('yandex-music-track')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                ></iframe>
                @break

            @case('yandex-music-album')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                ></iframe>
                @break

            @case('yandex-music-playlist')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                ></iframe>
                @break

            @case('coub')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                    allow="autoplay"
                ></iframe>
                @break

            @case('codepen')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                    scrolling="no"
                    allowfullscreen
                ></iframe>
                @break

            @case('pinterest')
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                    scrolling="no"
                ></iframe>
                @break

            @case('github')
                <script src="{{ $source }}"></script>
                @break

            @default
                <iframe
                    class="editorjs-embed__iframe"
                    src="{{ $embed }}"
                    width="{{ $width }}"
                    height="{{ $height }}"
                    frameborder="0"
                ></iframe>
        @endswitch
    </div>

    @if($caption)
        <div class="editorjs-embed__caption">{{ $caption }}</div>
    @endif
</div>
