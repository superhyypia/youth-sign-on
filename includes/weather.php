<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */


function weather_unavailable($label = "Weather unavailable") {
    return [
        "status" => "unknown",
        "label" => $label,
        "temp_min" => null,
        "temp_max" => null,
        "rain_total" => null,
        "wind_max" => null,
        "gusts_max" => null
    ];
}

function get_spin_weather($spin_date, $start_time) {
    $latitude = 53.0106;
    $longitude = -6.1119;

    $url = "https://api.open-meteo.com/v1/forecast"
        . "?latitude=" . $latitude
        . "&longitude=" . $longitude
        . "&hourly=temperature_2m,precipitation,wind_speed_10m,wind_gusts_10m"
        . "&timezone=Europe%2FDublin";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false || empty($response)) {
        return weather_unavailable();
    }

    $data = json_decode($response, true);

    if (!isset($data["hourly"]["time"])) {
        return weather_unavailable();
    }

    $start_ts = strtotime($spin_date . " " . $start_time);
    $end_ts = strtotime("+5 hours", $start_ts);

    $temps = [];
    $rain_values = [];
    $wind_values = [];
    $gust_values = [];

    foreach ($data["hourly"]["time"] as $i => $forecast_time) {
        $forecast_ts = strtotime($forecast_time);

        if ($forecast_ts >= $start_ts && $forecast_ts <= $end_ts) {
            if (isset($data["hourly"]["temperature_2m"][$i])) {
                $temps[] = $data["hourly"]["temperature_2m"][$i];
            }

            if (isset($data["hourly"]["precipitation"][$i])) {
                $rain_values[] = $data["hourly"]["precipitation"][$i];
            }

            if (isset($data["hourly"]["wind_speed_10m"][$i])) {
                $wind_values[] = $data["hourly"]["wind_speed_10m"][$i];
            }

            if (isset($data["hourly"]["wind_gusts_10m"][$i])) {
                $gust_values[] = $data["hourly"]["wind_gusts_10m"][$i];
            }
        }
    }

    if (empty($temps) && empty($rain_values) && empty($wind_values) && empty($gust_values)) {
        return weather_unavailable("Forecast unavailable");
    }

    $temp_min = !empty($temps) ? min($temps) : null;
    $temp_max = !empty($temps) ? max($temps) : null;
    $rain_total = !empty($rain_values) ? array_sum($rain_values) : null;
    $wind_max = !empty($wind_values) ? max($wind_values) : null;
    $gusts_max = !empty($gust_values) ? max($gust_values) : null;

    $status = "green";
    $label = "Good";

    if (
        ($wind_max !== null && $wind_max >= 40) ||
        ($gusts_max !== null && $gusts_max >= 55) ||
        ($rain_total !== null && $rain_total >= 8) ||
        ($temp_min !== null && $temp_min <= 1)
    ) {
        $status = "red";
        $label = "Poor";
    } elseif (
        ($wind_max !== null && $wind_max >= 25) ||
        ($gusts_max !== null && $gusts_max >= 40) ||
        ($rain_total !== null && $rain_total >= 2) ||
        ($temp_min !== null && $temp_min <= 5)
    ) {
        $status = "orange";
        $label = "Caution";
    }

    return [
        "status" => $status,
        "label" => $label,
        "temp_min" => $temp_min,
        "temp_max" => $temp_max,
        "rain_total" => $rain_total,
        "wind_max" => $wind_max,
        "gusts_max" => $gusts_max
    ];
}
?>