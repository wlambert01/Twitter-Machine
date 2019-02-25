<?php
session_start();
session_destroy();
header('Location: https://twitter.com/logout');