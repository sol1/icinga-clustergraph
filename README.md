# ClusterGraph Module for Icinga Web 2

This module provides visualization of Icinga 2 cluster zones and endpoints in a graphical format.

## Features

- API integration with Icinga 2.
- Graphical representation of Icinga 2 cluster zones and endpoints.
- Configuration via Icinga Web 2 web interface.

## Prerequisites

- Icinga Web 2
- Icinga 2 with API user credentials

## Installation

1. Clone the `clustergraph` module to the `modules` directory of Icinga Web 2.

```
git clone https://github.com/sol1/icinga-clustergraph.git /usr/share/icingaweb2/modules/clustergraph
```

2. Enable the module via the Icinga Web 2 web interface or using the CLI:

```
icingacli module enable clustergraph
```

## Configuration

1. Navigate to `Configuration` -> `Modules` -> `clustergraph` -> `Config`.
2. Provide the necessary API details including API User, API Password, and API Endpoint.
3. Save changes.

## Usage

Once configured, navigate to the ClusterGraph section in Icinga Web 2 to view the graphical representation of Icinga 2
cluster zones and endpoints.

## Contributing

If you'd like to contribute to the development of this module, please submit pull requests or issues
to https://github.com/sol1/icinga-clustergraph.

## License

All code here is licensed under GPL V2

