# Alfred Pushover

This workflow for [Alfred 3](https://www.alfredapp.com/) allows you to send notifcations to your mobile devices with [Pushover](https://pushover.net/).

![Preview of Pushover workflow](docs/alfred-pushover.png)

The workflow has to keywords `push <text to push>` and `pushc` to push your current clipboard. You can than push it to all your devices or select a sepcific one (see step 4. under Installation).
If you push an URL the workflow will use the Pushover feature for pushing URLs so it can be directly be opend in the App.

## Installation

1. Head over to Pushover an [create a new application](https://pushover.net/apps/build). Set a name and choose `Script` in the Type-dropdown. Copy the API Token of the newly created application.
2. [Download the Workflow here](https://github.com/stroebjo/alfred-recent/releases/latest) and open it.
3. Head over to the settings pane of the Pushover worklfow and configure the variables with your `USER_KEY` and the `API_TOKEN` of the App you just created.
4. Optionally you can enter the names of your devices so you can push notification to a specfic device.
