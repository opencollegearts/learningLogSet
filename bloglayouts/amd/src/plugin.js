import {getTinyMCE} from 'editor_tiny/loader';
import {getPluginMetadata} from 'editor_tiny/utils';
import {
    component,
    pluginName,
} from './common';
import {getSetup as getCommandSetup} from './commands';
import * as Configuration from './configuration';

export default new Promise((resolve) => {
    (async() => {
        const [
            tinyMCE,
            pluginMetadata,
            setupCommands,
        ] = await Promise.all([
            getTinyMCE(),
            getPluginMetadata(component, pluginName),
            getCommandSetup(),
        ]);

        tinyMCE.PluginManager.add(pluginName, (editor) => {
            setupCommands(editor);
            return pluginMetadata;
        });

        resolve([pluginName, Configuration]);
    })();
});

