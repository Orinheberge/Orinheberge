<?php

return [
    "minecraft" => [
        "name" => "Minecraft Free",
        "node" => 2,
        "nest" => 1,
        "egg" => 2,
        "image" => "ghcr.io/pterodactyl/yolks:java_25",
        "startup" => "java -Xms2G -Xmx6G -XX:+UseG1GC -XX:+ParallelRefProcEnabled -XX:MaxGCPauseMillis=100 -XX:+UnlockExperimentalVMOptions -XX:+DisableExplicitGC -XX:+AlwaysPreTouch -XX:G1NewSizePercent=30 -XX:G1MaxNewSizePercent=40 -XX:G1HeapRegionSize=8M -XX:G1ReservePercent=20 -XX:G1HeapWastePercent=5 -XX:G1MixedGCCountTarget=4 -XX:InitiatingHeapOccupancyPercent=15 -XX:G1MixedGCLiveThresholdPercent=90 -XX:G1RSetUpdatingPauseTimePercent=5 -XX:SurvivorRatio=32 -XX:+PerfDisableSharedMem -Dterminal.jline=false -Dterminal.ansi=true -Dbstats.enabled=true -jar {{SERVER_JARFILE}} nogui",
        "env" => [
            "SERVER_JARFILE" => "server.jar",
            "MINECRAFT_VERSION" => "latest",
            "BUILD_NUMBER" => "latest"
        ],
        "ram" => 6144,
        "disk" => 30000,
        "cpu" => 400
    ],

    "nodejs" => [
        "name" => "NodeJS Free",
        "node" => 1,
        "nest" => 7,
        "egg" => 27,
        "image" => "ghcr.io/ptero-eggs/yolks:nodejs_25",
        "startup" => "if [[ -d .git ]] && [[ {{AUTO_UPDATE}} == \"1\" ]]; then git pull; fi; if [[ ! -z {{NODE_PACKAGES}} ]]; then npm install {{NODE_PACKAGES}}; fi; if [[ ! -z {{UNNEST_NODE}} ]]; then npm install; fi; /usr/local/bin/node /home/container/{{MAIN_FILE}}",
        "env" => [
            "MAIN_FILE" => "index.js",
            "USER_UPLOAD" => "1",
            "AUTO_UPDATE" => "0"
        ],
        "ram" => 2048,
        "disk" => 10000,
        "cpu" => 200
    ],

    "java" => [
        "name" => "JAVA FREE",
        "node" => 1,
        "nest" => 7,
        "egg" => 27,
        "image" => "ghcr.io/ptero-eggs/yolks:java_25",
        "startup" => 'java -Dterminal.jline=false -Dterminal.ansi=true -jar {{JARFILE}}',
        "env" => [
		"JARFILE" => "sneakyhub.jar",
    "USER_UPLOAD" => "0", // Or "1" depending on if user uploads are allowed
    "AUTO_UPDATE" => "0", // Or "1" if auto-update is enabled
    "MAIN_FILE" => "sneakyhub.jar" // Usually matches your main jar file
],
        "ram" => 2048,
        "disk" => 10000,
        "cpu" => 200
    ],

    "python" => [
        "name" => "Python free",
        "node" => 1,
        "nest" => 8,
        "egg" => 28,
        "image" => "ghcr.io/ptero-eggs/yolks:python_3.13",
        "startup" => 'if [[ -d .git ]] && [[ "{{AUTO_UPDATE}}" == "1" ]]; then git pull; fi; if [[ ! -z "{{PY_PACKAGES}}" ]]; then pip install -U --prefix .local {{PY_PACKAGES}}; fi; if [[ -f /home/container/${REQUIREMENTS_FILE} ]]; then pip install -U --prefix .local -r ${REQUIREMENTS_FILE}; fi; /usr/local/bin/python /home/container/{{PY_FILE}}',
        "env" => [
            "PY_FILE" => "index.py",
            "AUTO_UPDATE" => "0",
            "PY_PACKAGES" => "",
            "USER_UPLOAD" => "0",
            "REQUIREMENTS_FILE" => "requirements.txt"
        ],
        "ram" => 2048,
        "disk" => 10000,
        "cpu" => 200
    ],

    "hytale" => [
        "name" => "Hytale free",
        "price" => 0,
        "node" => 2,
        "nest" => 6,
        "egg" => 17,
        "image" => "ghcr.io/pterodactyl/games:hytale",
        "startup" => 'java $( ((USE_AOT_CACHE)) && printf %s "-XX:AOTCache=Server/HytaleServer.aot" ) -Xms128M $( ((SERVER_MEMORY)) && printf %s "-Xmx${SERVER_MEMORY}M" ) -jar Server/HytaleServer.jar $( ((HYTALE_ALLOW_OP)) && printf %s "--allow-op" ) $( ((HYTALE_ACCEPT_EARLY_PLUGINS)) && printf %s "--accept-early-plugins" ) $( ((DISABLE_SENTRY)) && printf %s "--disable-sentry" ) --auth-mode ${HYTALE_AUTH_MODE} --assets Assets.zip --bind 0.0.0.0:${SERVER_PORT}',
        "env" => [
            "SERVER_MEMORY" => "6144",
            "USE_AOT_CACHE" => "1",
            "HYTALE_ALLOW_OP" => "0",
            "HYTALE_ACCEPT_EARLY_PLUGINS" => "0",
            "DISABLE_SENTRY" => "0",
            "HYTALE_AUTH_MODE" => "authenticated",
            "HYTALE_PATCHLINE" => "release",
            "INSTALL_SOURCEQUERY_PLUGIN" => "1",
            "DL_VERSION" => "latest",
            "BUILD_NUMBER" => "1"
        ],
        "ram" => 6144,
        "disk" => 30000,
        "cpu" => 400
    ],

    "php" => [
        "name" => "PHP Free",
        "node" => 1,
        "nest" => 5,
        "egg" => 18,
        "image" => "ym0t/pterodactyl-nginx-egg:8.5-latest",
        "startup" => "./start-modules.sh",
        "env" => [
            "AUTOUPDATE_STATUS" => "1",
            "AUTOUPDATE_FORCE" => "0",
            "PHP_VERSION" => "8.4",
            "WORDPRESS" => "0",
            "LOGCLEANER_STATUS" => "1",
            "GIT_STATUS" => "0",
            "GIT_ADDRESS" => "",
            "GIT_BRANCH" => "",
            "USERNAME" => "",
            "ACCESS_TOKEN" => "",
            "CLOUDFLARED_STATUS" => "0",
            "CLOUDFLARED_TOKEN" => "",
            "COMPOSER_STATUS" => "0",
            "COMPOSER_MODULES" => "",
            "CRON_STATUS" => "0",
            "CRON_CONFIG_FILE" => "/home/container/crontab",
            "CERTBOT_STATUS" => "0",
            "CERTBOT_EMAIL" => "",
            "CERTBOT_DOMAIN" => "",
            "CERTBOT_WEBROOT_PATH" => "/home/container/www",
            "CERTBOT_STAGING" => "0",
            "CERTBOT_FORCE_RENEWAL" => "0"
        ],
        "ram" => 1024,
        "disk" => 10000,
        "cpu" => 100
    ]
];