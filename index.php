<!DOCTYPE HTML>
<html>
<head>
    <title>Live dashboard</title>
    <script
            src="https://code.jquery.com/jquery-3.2.1.min.js"
            integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
            crossorigin="anonymous"></script>

    <style type="text/css">
        body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        iframe {
            border: 0;
        }
        .fullscreen {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>

<div class="app loading">
    Loading...
</div>

<script type="text/javascript">
    class ScenarioHandlers
    {
        static iframe(next, $app, options){
            if (typeof options !== 'object') {
                options = {};
            }

            $app.html(
                $('<iframe src="'+options.url+'" class="fullscreen"></iframe>').on('load', function() {
                    let $iframe = $(this);
                    let iframeWindow = $iframe.get(0).contentWindow || $iframe.get(0).contentDocument;

                    // Zoom
                    if (options.zoom) {
                        $iframe.contents().find('body').css({
                            '-ms-zoom': options.zoom,
                            '-moz-transform': 'scale('+options.zoom+')',
                            '-moz-transform-origin': 'center 0',
                            '-o-transform': 'scale('+options.zoom+')',
                            '-o-transform-origin': 'center 0',
                            '-webkit-transform': 'scale('+options.zoom+')',
                            '-webkit-transform-origin': 'center 0',
                        });
                    }

                    // Auto scroll
                    if (options.autoScroll) {
                        let $body = $iframe.contents().find('body');
                        var documentHeight = $body.get(0).scrollHeight;

                        $body.animate(
                            { scrollTop: documentHeight },
                            {
                                duration: (documentHeight / options.autoScrollSpeed || 20) * 1000,
                                easing: "linear",
                                complete: next,
                            }
                        );
                    }
                })
            );
        }
    }

    $(function() {
        var scenarioTimer;

        const $app = $('.app');

        const scenarios = [
            {
                title: 'Test',
                timeout: 30000,
                handler: (next) => ScenarioHandlers.iframe(next, $app, {
                    url: 'http://pascal.lyon.novius.fr/git/pulls/',
                    autoScroll: true,
                    autoScrollSpeed: 50,
                    zoom: 1.2,
                }),
            },
            {
                title: 'Test',
                timeout: 20000,
                handler: (next) => ScenarioHandlers.iframe(next, $app, {
                    url: 'http://www.novius.com',
                }),
            },
        ];

        function run() {
            let scenario = scenarios[Math.floor(Math.random() * scenarios.length)];
            console.log(scenario);

            // Checks requirements
            if (!scenario.handler) {
                throw "Missing handler for scenario "+scenario.title;
            }
            if (typeof scenario.handler !== 'function') {
                throw "Handler must be a valid callback for scenario "+scenario.title;
            }

            // Executes scenario
            scenario.handler.apply($app, [next]);

            // Sets timeout
            scenarioTimer = setTimeout(next, scenario.timeout || 10);
        }

        function next()
        {
            // Clears the timeout in case the previous scenario triggered the next one before the timeout is triggered
            if (scenarioTimer) {
                clearTimeout(scenarioTimer);
            }

            // Runs next scenario
            run();
        }

        run();
    });

</script>
</body>
</html>
