# VirtFusion

Sell virtual servers on a [VirtFusion](https://virtfusion.com) panel from WemX. Each order creates a VirtFusion user when needed and deploys a server from a VirtFusion package.

## Features

- Connection with the VirtFusion URL and an API token
- VirtFusion package picked from the enabled packages on your panel
- Hypervisor group and number of IPv4 addresses per package
- Create a VirtFusion user for the customer and email the login
- One-click "Login to Panel" from the order page
- Suspend, unsuspend, terminate, and package upgrades

## Install

Install from the WemX marketplace, or download `Virtfusion.zip` from a GitHub release and place the `Virtfusion` folder at `extensions/Servers/Virtfusion`. Then enable **VirtFusion**.

Publishing a release builds `Virtfusion.zip`. Unzipping it creates a folder named `Virtfusion`. GitHub’s own “Source code” archive still unpacks to `server-virtfusion-<tag>`. Use `Virtfusion.zip`.

## Connection

Set the panel URL without a trailing slash, for example `https://panel.example.com`, and an API token from **VirtFusion → Settings → API**.

## Packages

Pick the VirtFusion package and enter the hypervisor group ID and the number of IPv4 addresses. Upgrading an order to another WemX package moves the server to that package's VirtFusion package.

VirtFusion users are linked to WemX users by their WemX user id (`extRelationId`), which is also used for the one-click login.
