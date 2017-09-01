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
        /**
         * Displays an iframe
         *
         * Available options :
         * {
         *     url: 'http://example.com',
         *     autoScroll: true,
         *     autoScrollSpeed: 50,
         *     zoom: 1.2,
         * }
         *
         * @param next
         * @param $app
         * @param options
         */
        static iframe(next, $app, options){
            if (typeof options !== 'object') {
                options = {};
            }

            $app.html(
                $('<iframe src="'+options.url+'" class="fullscreen"></iframe>').on('load', function() {
                    const $iframe = $(this);
                    const iframeWindow = $iframe.get(0).contentWindow || $iframe.get(0).contentDocument;

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

                        setTimeout(function autoScroll() {
                            const $body = $iframe.contents().find('body');
                            const documentHeight = $body.get(0).scrollHeight;
                            $body.animate(
                                { scrollTop: documentHeight },
                                {
                                    duration: (documentHeight / options.autoScrollSpeed || 20) * 1000,
                                    easing: "linear",
                                    complete: next,
                                }
                            );
                        }, options.autoScrollDelay || 1);
                    }
                })
            );
        }
    }

    $(function() {
        var scenarioTimer;

        const $app = $('.app');

        /**
         * Available scenarios
         */
        const scenarios = [
            {
                title: 'Pull requests',
                timeout: 60000,
                handler: (next) => ScenarioHandlers.iframe(next, $app, {
                    url: 'http://pascal.lyon.novius.fr/git/pulls/',
                    autoScroll: true,
                    autoScrollSpeed: 50,
                    zoom: 1.2,
                }),
            },
            {
                title: 'Novius.com',
                timeout: 60000,
                handler: (next) => ScenarioHandlers.iframe(next, $app, {
                    url: 'http://www.novius.com',
                }),
            },
            {
                title: 'Laravel.com',
                timeout: 20000,
                handler: (next) => ScenarioHandlers.iframe(next, $app, {
                    url: 'https://laravel.com/',
                }),
            },
        ];

        console.log('Available scenarios', scenario);

        /**
         * Runs a random scenario
         */
        function run() {

            // Gets a random scenario
            let scenario = scenarios[Math.floor(Math.random() * scenarios.length)];
            console.log('Scenario', scenario);

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

        /**
         * Runs the next scenario
         */
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
