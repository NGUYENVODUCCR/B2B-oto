<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>B2B Swagger API</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/swagger-ui-dist/swagger-ui.css"
    />
</head>

<body>

<div id="swagger-ui"></div>

<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist/swagger-ui-bundle.js"></script>

<script>

window.onload = function () {

    const ui = SwaggerUIBundle({

       url: "http://localhost/B2B-dev/web/app/plugins/b2b-core/swagger.php"

        dom_id: '#swagger-ui',

        deepLinking: true,

        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIBundle.SwaggerUIStandalonePreset
        ],

        layout: "BaseLayout"
    });

    window.ui = ui;
};

</script>

</body>
</html>