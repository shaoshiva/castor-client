# Castor Client

The client used to display the screens broadcasted by a [Castor Server](https://github.com/shaoshiva/castor-server).

## Install

`npm install "git+ssh://git@github.com:shaoshiva/castor-client.git#master" --save`

## Usage

```html
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="UTF-8">
    <title>Live dashboard</title>
    <script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/foundation/6.4.3/css/foundation.min.css" />
    <link rel="stylesheet" href="node_modules/castor-client/css/main.css" />
    <script type="text/javascript" src="node_modules/castor-client/index.js"></script>
</head>
<body>

<div class="app loading">
    <img class="logo" src="http://www.example.org/logo.png" alt="logo"/>
</div>

<script type="text/javascript">
    $(function()
    {
        // Builds the client
        const client = new CastorClient($('.app'), {
            token: 'YOUR_TOKEN',
        });

        // Setups the admin command
        client.setupAdminCommands();

        // Listen to server events
        client.start();
    });
</script>
</body>
</html>
```

`YOUR_TOKEN` must be the same as the one configured on your server.
