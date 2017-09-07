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
     iframe: function(next, options){
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
      html: function(next, options){
            if (typeof options !== 'object') {
                options = {};
            }

            this.html(options.content || '');
        }
    };

    $(function() {
        var scenarioTimer;

        const $app = $('.app');

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
        } catch(e) {
            console.warn(e);
        }

        console.log('Received message from server:', data);

        // Plain text data
        if (typeof data === 'string') {
            return ;
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
                    nextScenario(data.data);
                    break;
            }
        }

        // Unknown format
        else {
            console.warn('Unknown data format :', data);
        }
    };

    // Waits for the connection to be opened
    ws.onopen = function() {
        console.log("Connection to server successfuly established.");

        // Greetings
        ws.send('Hello, I am the client.');

        // Gets the current scenario
        ws.send({
            action: 'getCurrentScenario'
        });
    };


        /**
         * Runs a scenario
         */
        function runScenario(scenario) {
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

            // Executes scenario
            handler.apply($app, [nextScenario, scenario.handlerOptions || {}]);

//            // Sets timeout
//            scenarioTimer = setTimeout(nexScenariot, scenario.timeout || 10);
        }

        /**
         * Runs the next scenario
         */
        function nextScenario(scenario)
        {
//            // Gets a random scenario if none specified
//            if (typeof scenario === 'undefined') {
//                scenario = scenarios[Math.floor(Math.random() * scenarios.length)];
//            }

            // Clears the timeout in case the previous scenario triggered the next one before the timeout is triggered
            if (scenarioTimer) {
                clearTimeout(scenarioTimer);
            }

            // Run it
            runScenario(scenario);
        }

        // Runs the first scenario
//        nextScenario();
    });

</script>
</body>
</html>
