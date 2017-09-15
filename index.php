<!DOCTYPE HTML>
<html>
<head>
    <meta charset="UTF-8">
    <title>Live dashboard</title>
    <script
            src="https://code.jquery.com/jquery-3.2.1.min.js"
            integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
            crossorigin="anonymous"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/foundation/6.4.3/css/foundation.min.css" />

    <style type="text/css">
        * {
            box-sizing: border-box;
        }

        body {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
            display: block;
            min-height: 100vh;
            font-family: 'Ubuntu', sans-serif;
            background-image: -webkit-radial-gradient(100% 250% at 50% -25%, #6eaad1 0%, #0a3772 60%);
            background-image: radial-gradient(100% 250% at 50% -25%, #6eaad1 0%, #0a3772 60%);
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

        .loading .logo {
            position: absolute;
            top: 2rem;
            left: 50%;
            -webkit-transform: translateX(-50%);
            transform: translateX(-50%);
            max-width: 350px;
        }
    </style>
</head>
<body>

<div class="app loading">
    <img class="logo" src="http://www.novius.com/static/apps/agency_template/img/logo.png" alt="logo"/>
</div>

<script type="text/javascript">

    const REQUEST_TOKEN = 'V?Tv)k9hGvM?~${MAk5sT%NfdN\N~!$TdZGuB%cD';

    function createWebSocket()
    {
        const wsUrl = 'ws://10.20.70.9:8099';

        const ws = new WebSocket(wsUrl);

        // Overrides send method
        ws.nativeSend = ws.send;
        ws.send = function(message) {
            console.log('Sending message to server:', message);
            ws.nativeSend(JSON.stringify({
                token: REQUEST_TOKEN,
                data: message,
            }));
        };

        // Logs erros
        ws.onerror = function(error) {
            console.error(error);
        };

        return ws;
    }

    const scenarioHandlers = {
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
         * @param options
         */
        iframe: function(next, options)
        {
            if (typeof options !== 'object') {
                options = {};
            }

            this.html(
                $('<iframe src="'+options.url+'" class="fullscreen"></iframe>').on('load', function() {
                    const $iframe = $(this);
                    const iframeWindow = $iframe.get(0).contentWindow || $iframe.get(0).contentDocument;

//                 // Zoom
//                 if (options.zoom) {
//                     $iframe.contents().find('body').css({
//                        '-ms-zoom': options.zoom,
//                        '-moz-transform': 'scale('+options.zoom+')',
//                        '-moz-transform-origin': 'center 0',
//                        '-o-transform': 'scale('+options.zoom+')',
//                        '-o-transform-origin': 'center 0',
//                        '-webkit-transform': 'scale('+options.zoom+')',
//                        '-webkit-transform-origin': 'center 0',
//                      });
//                  }

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
                                }
                            );
                        }, options.autoScrollDelay || 1);
                    }
                })
            );
        },

        /**
         * Displays a message
         *
         * @param next
         * @param options
         */
        message: function(next, options)
        {
            if (typeof options !== 'object') {
                options = {};
            }

            this.html(options.content || '');
        },

        /**
         * Displays HTML content
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
         * @param options
         */
        html: function(next, options)
        {
            if (typeof options !== 'object') {
                options = {};
            }

            this.html(options.content || '');
        },
    };

    $(function()
    {
        const $app = $('.app');

        var lostConnections, failedConnections, failedConsecutiveConnection = 0;

        var restarting = false;

        function start()
        {
            var connectionOpened = false;

            try {
                console.log('Opening connection to server...');
                const ws = createWebSocket();

                // Handles actions sent by server
                ws.onmessage = function incoming(message) {
                    var data = message.data;

                    // Tries parsing the message as JSON
                    try {
                        if (typeof data === 'string' && data[0] === '{') {
                            const dataObject = JSON.parse(data);
                            if (typeof dataObject === 'object') {
                                data = dataObject;
                            }
                        }
                    } catch (e) {
                        console.warn(e);
                    }

                    console.log('Received message from server:', data);

                    // Plain text data
                    if (typeof data === 'string') {
                        return;
                    }

                    // Object data
                    else if (typeof data === 'object') {

                        // Checks if action is specified
                        if (!data.action) {
                            console.warn('No action specified for object data.');
                            return;
                        }

                        // Handles actions
                        switch (data.action) {

                            // Resets (reloads current page)
                            case 'reset':
                                window.location.reload(false);
                                break;

                            // Runs a scenario
                            case 'runScenario':
                                if (data.data) {
                                    runScenario(data.data);
                                } else {
                                    console.warn('No scenario.');
                                }
                                break;
                        }
                    }

                    // Unknown format
                    else {
                        console.warn('Unknown data format :', data);
                    }
                };

                // Waits for the connection to be opened
                ws.onopen = function () {
                    connectionOpened = true;
                    restarting = false;
                    failedConsecutiveConnection = 0;

                    console.log("Connection to server successfuly established.");

                    // Greetings
                    ws.send('Hello, I am the client.');

                    // Gets the current scenario
                    ws.send({
                        action: 'getCurrentScenario'
                    });
                };

                // Tries to reconnect when connection is lost
                ws.onclose = function () {
                    // Connection lost
                    console.log('Server connection lost.');

                    if (connectionOpened) {
                        lostConnections++;
                    }
                    if (!connectionOpened) {
                        failedConnections++;
                    }
                    if (!connectionOpened && restarting) {
                        failedConsecutiveConnection++;
                    }

                    // Restart server
                    restart();
                };

            } catch (exception) {
                restart();
            }
        }

        /**
         * Tries to restart the server after a delay
         *
         * @param delay
         */
        function restart(delay)
        {
            restarting = true;

            // Reload entire page after 5 failed restarts
            if (failedConsecutiveConnection >= 5) {
                console.log('Failed to connect after 5 attempts.');
                console.log('Refreshing page in 5 seconds...');
                setTimeout(function() {
                    window.location.reload(false);
                }, 5000);
            }

            // Try to reconnect in 5 seconds
            else {
                attemptsLeft = 5 - failedConsecutiveConnection;
                console.log('Trying to reconnect to server in 5 seconds... ('+(attemptsLeft === 1 ? 'last attempt' : ''+attemptsLeft+' attempts left')+')');
                setTimeout(function () {
                    start()
                }, delay || 5000);
            }
        }

        // Listen to server events
        start();

        /**
         * Runs a scenario
         */
        function runScenario(scenario)
        {
            console.log('Running scenario:', scenario);

            // Checks requirements
            if (!scenario.handler) {
                throw "Missing handler for scenario "+scenario.title;
            }

            var handler = scenario.handler;

            // Converts handler method to function
            if (typeof handler === 'string') {
                if (typeof scenarioHandlers[handler] !== 'undefined') {
                    handler = scenarioHandlers[handler];
                }
            }

            if (typeof handler !== 'function') {
                throw "Handler must be a valid callback for scenario "+scenario.title;
            }

            // Executes the scenario
            handler.apply($app, [runScenario, scenario.handlerOptions || {}]);
        }

        // @todo remove this feature from client
        window.runNextScenario = (id) => {
            console.log('Telling the server to run the next scenario...');
            ws.send({
                action: 'runNextScenario',
                id: id,
            });
        };
    });

</script>
</body>
</html>
