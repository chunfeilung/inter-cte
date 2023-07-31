![](public/inter-cte.svg)

# Inter-CTE
Inter-CTE (pronounced as “intershitty”) is the only Dutch travel planner that’s
named after an _Intercity_ (express) train, but runs at the speed of a
_Stoptrein_ (local train). It’s a containerised web application that can be run
locally, so the actual speed will depend on the specs of your machine and the
amount of bloatware that you or your OEM have installed on it.

[1]: https://chuniversiteit.nl/flat-earth/inter-cte

## Features
Inter-CTE features three ways to plan your train travels in, to and from the
Netherlands for the current date:

- Lists of departing trains for each station in the Dutch train network
- A simple SQL interface that lets you write your own queries
- A stupid user interface for normies

More information can be found in [the accompanying blog post][1].

![Screenshot of Inter-CTE’s web interface](https://chuniversiteit.nl/images/content/2023/inter-cte-travel-advice.png)

## Getting started
First, clone this repository and run the following command to build and start
the application using [Docker Compose](https://docs.docker.com/compose/):

```
docker compose up -d
```

Once the application has started, run the `reset` command:

```
docker compose exec app bin/console app:reset
```

This will set up the application, and import the timetable data for the current
date. Depending on your machine and the quality of your network connection, this
process may take anywhere from a few minutes to more than an hour (when you use
the free Wi-Fi on Dutch trains).

Once application setup has completed, browse to http://127.0.0.1:8000 to get
started!

## Contributing
The version you see here is the final, definitive, complete, “no touchies”
version of Inter-CTE. You’re free to fork the project and make modifications,
but I won’t accept pull requests.

I also do not welcome bug reports. This project was created with the sole
purpose of shitposting. Anything that even remotely resembles a bug should be
considered a feature, so is anything that, by chance, happens to work as
expected.
