"use strict";

(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        // Node. Does not work with strict CommonJS, but
        // only CommonJS-like environments that support module.exports,
        // like Node.
        module.exports = factory();
    } else {
        // Browser globals (root is window)
        root.NoviusLiveClient = factory();
    }
}(this, function () {

    const defaultScenarioHandlers = {
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
                $('<iframe sandbox="allow-same-origin allow-scripts allow-forms" src="'+options.url+'" class="fullscreen"></iframe>')
                    .on('load', function() {
                        const $iframe = $(this);
                        const $body = $iframe.contents().find('body');

                        $body.css({ overflow: 'hidden'});

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
                                if ($body.length) {
                                    const documentHeight = $body.get(0).scrollHeight;
                                    $body.animate(
                                        {
                                            scrollTop: documentHeight
                                        },
                                        {
                                            duration: (documentHeight / options.autoScrollSpeed || 20) * 1000,
                                            easing: 'linear',
                                        }
                                    );
                                }
                            }, options.autoScrollDelay || 2000);
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

    /**
     * The client
     */
    class NoviusLiveClient
    {
        constructor($container, options) {
            this.scenarioHandlers = {};
            this.connectionOpened = false;
            this.lostConnections = false;
            this.failedConnections = false;
            this.failedConsecutiveConnection = false;
            this.restarting = false;

            // Sets the container
            this.$container = $container;

            // Sets the options
            this.options = Object.assign({
                websocket: {
                    url: 'ws://localhost:8099',
                },
            }, options);

            // Registers the default scenario handlers
            for (let name in defaultScenarioHandlers) {
                this.registerScenarioHandler(name, defaultScenarioHandlers[name]);
            }

        }

        /**
         * Registers a scenario handler
         *
         * @param name
         * @param callback
         */
        registerScenarioHandler(name, callback) {
            this.scenarioHandlers[name] = callback;
        }

        /**
         * Gets a registered scenario handler
         *
         * @param name
         * @returns {*}
         */
        getScenarioHandler(name) {
            return this.scenarioHandlers[name];
        }

        /**
         * Creates the websocket connection
         *
         * @returns {WebSocket}
         */
        createWebSocket() {
            const ws = new WebSocket(this.options.websocket.url);

            // Overrides the send method
            ws.nativeSend = ws.send;
            ws.send = (payload) => {
                console.log('Sending message with payload to server: ', payload);
                ws.nativeSend(JSON.stringify({
                    token: this.options.token,
                    payload: payload,
                }));
            };

            // Logs errors
            ws.onerror = (error) => {
                console.error(error);
            };

            return ws;
        }

        /**
         * Starts the client
         */
        start() {
            try {
                // Creates the websocket connection
                console.log('Opening connection to server...');
                this.connection = this.createWebSocket();

                // Waits for the connection to be opened
                this.connection.onopen = () => {
                    this.connectionOpened = true;
                    this.restarting = false;
                    this.failedConsecutiveConnection = 0;

                    console.log('Connection to server successfuly established.');

                    // Greetings
                    this.connection.send('Hello, I am the client.');

                    // Gets the current scenario
                    this.connection.send({
                        command: 'requestCurrentScenario'
                    });
                };

                // Tries to reconnect when connection is lost
                this.connection.onclose = () => {
                    // Connection lost
                    console.log('Server connection lost.');

                    if (this.connectionOpened) {
                        this.lostConnections++;
                    }
                    if (!this.connectionOpened) {
                        this.failedConnections++;
                    }
                    if (!this.connectionOpened && this.restarting) {
                        this.failedConsecutiveConnection++;
                    }

                    this.connection = null;

                    // Restarts the server
                    this.restart();
                };

                // Handles incoming messages
                this.connection.onmessage = (event) => {
                    this.handleMessage(event.data);
                };
            }
            // Handles exceptions
            catch (exception) {
                this.restart();
            }
        }

        /**
         * Stops the client
         */
        stop()
        {
            // Closes the websocket connection
            if (this.connection) {
                this.connection.close();
                this.connection = null;
            }
        }

        /**
         * Tries to restart the client after a delay
         *
         * @param delay
         */
        restart(delay)
        {
            this.restarting = true;

            // Reload entire page after 5 failed restarts
            if (this.failedConsecutiveConnection >= 5) {
                console.log('Failed to connect after 5 attempts.');
                console.log('Refreshing page in 5 seconds...');
                setTimeout(() => window.location.reload(false), 5000);
            }

            // Try to reconnect in 5 seconds
            else {
                let attemptsLeft = 5 - this.failedConsecutiveConnection;
                console.log('Trying to reconnect to server in 5 seconds... ('+(attemptsLeft === 1 ? 'last attempt' : ''+attemptsLeft+' attempts left')+')');
                setTimeout(() => this.start(), delay || 5000);
            }
        }

        /**
         * Handles an incoming message
         */
        handleMessage(message) {
            // Tries parsing the message as JSON
            try {
                if (typeof message === 'string' && message[0] === '{') {
                    const messageObject = JSON.parse(message);
                    if (typeof messageObject === 'object') {
                        message = messageObject;
                    }
                }
            } catch (e) {
                console.warn(e);
            }

            // Plain text payload
            if (typeof message === 'string') {
                console.log('Received message from server: ', message);
            }

            // Object payload
            else if (typeof message === 'object') {
                console.log('Received message with payload from server: ', message);

                // Gets the payload
                let payload = message.payload;
                if (typeof payload !== 'object') {
                    console.warn('Invalid payload: object expected.');
                    return ;
                }

                // Checks if command is specified
                if (!payload.command) {
                    console.warn('No command specified.');
                    return;
                }

                // Handles commands
                switch (payload.command) {

                    // Resets (reloads current page)
                    case 'reset':
                        window.location.reload(false);
                        break;

                    // Runs a scenario
                    case 'runScenario':
                        if (payload.params && payload.params.scenario) {
                            this.runScenario(payload.params.scenario);
                        } else {
                            console.warn('No scenario.');
                        }
                        break;
                }
            }

            // Unknown format
            else {
                console.warn('Invalid message: string or object expected.');
            }
        }

        /**
         * Runs a scenario
         */
        runScenario(scenario)
        {
            console.log('Running scenario:', scenario);

            // Checks requirements
            if (!scenario.handler) {
                throw 'Missing handler for scenario '+scenario.title;
            }

            let handler = scenario.handler;

            // Converts handler method to function
            if (typeof handler === 'string') {
                handler = this.getScenarioHandler(handler) || handler;
            }

            if (typeof handler !== 'function') {
                throw 'Handler must be a valid callback for scenario '+scenario.title;
            }

            // Executes the scenario
            handler.apply(this.$container, [this.runScenario, scenario.handler_options || {}]);
        }

        /**
         * Runs the given callback if the websocket is available
         *
         * @param callback
         */
        getConnection(callback) {
            if (this.connection) {
                callback(this.connection);
            } else {
                console.warn('Connection to server not available.');
            }
        }

        /**
         * Setups the admin commands
         */
        setupAdminCommands()
        {
            /**
             * Admin commands (temporary features)
             *
             * @type {{}}
             */
            window.admin = {
                /**
                 * Runs a new scenario
                 */
                reset: () => {
                    console.log('Telling the server to reset...');
                    this.getConnection((connection) => {
                        connection.send({
                            command: 'reset',
                        });
                    })
                },

                /**
                 * Runs the given scenario
                 *
                 * @param id ID of scenario
                 * @param options [Optional] Scenario options (eg. display_timeout, handler options...)
                 */
                runScenario: (id, options) => {
                    console.log('Telling the server to run the given scenario...');
                    this.getConnection((connection) => {
                        connection.send({
                            command: 'runScenario',
                            params: {
                                id: id,
                                options: options || {},
                            }
                        });
                    });
                },

                /**
                 * Runs a random scenario
                 */
                runRandomScenario: () => {
                    console.log('Telling the server to run a random scenario...');
                    this.getConnection((connection) => {
                        connection.send({
                            command: 'runRandomScenario',
                        });
                    });
                },

                /**
                 * Runs the given scenario
                 *
                 * @param options Scenario options (eg. display_timeout, handler options...)
                 */
                createScenario: (options) => {
                    console.log('Telling the server to create the given scenario...');
                    this.getConnection((connection) => {
                        connection.send({
                            command: 'createScenario',
                            params: {
                                scenario: options,
                                run: false,
                            },
                        });
                    });
                },

                /**
                 * Runs the given scenario
                 *
                 * @param options Scenario options (eg. display_timeout, handler options...)
                 */
                createAndRunScenario: (options) => {
                    console.log('Telling the server to create and run the given scenario...');
                    this.getConnection((connection) => {
                        connection.send({
                            command: 'createScenario',
                            params: {
                                scenario: options,
                                run: true,
                            },
                        });
                    });
                },

                /**
                 * Displays the given message
                 *
                 * @param message
                 * @param options Scenario options (eg. display_timeout, handler options...)
                 */
                displayMessage: (message, options) => {
                    console.log('Telling the server to create and run the given scenario...');
                    this.getConnection((connection) => {
                        connection.send({
                            command: 'createScenario',
                            params: {
                                scenario: Object.assign({
                                    name: 'A temporary message',
                                    handler: 'message',
                                    handler_options: {
                                        message,
                                    }
                                }, options || {}),
                                run: true,
                            },
                        });
                    });
                },
            };
        }
    }

    return NoviusLiveClient;
}));
