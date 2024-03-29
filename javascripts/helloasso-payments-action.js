/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-helloasso-payments-table
 */

import HpfTable from './components/HpfTable.js'
import SpinnerLoader from '../../bazar/presentation/javascripts/components/SpinnerLoader.js'

const rootsElements = ['.dynamic-hpf-helloasso-payments-action'];
const isVueJS3 = (typeof window.Vue.createApp == "function");

let appParams = {
    components: {HpfTable,SpinnerLoader},
    data(){
        return {
            isLoading: true
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (isVueJS3){
        let app = window.Vue.createApp(appParams)
        app.config.globalProperties.wiki = window.wiki
        app.config.globalProperties._t = window._t
        rootsElements.forEach(elem => {
            app.mount(elem)
        })
    } else {
        window.Vue.prototype.wiki = window.wiki
        window.Vue.prototype._t = _t;
        rootsElements.forEach(elem => {
            new Vue({
                ...{el:elem},
                ...appParams
            })
        })
    }
})