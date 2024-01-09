/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-receipts-creation
 */

import asyncHelper from '../../../alternativeupdatej9rem/javascripts/asyncHelper.js'

let rootsElements = ['.receipts-field-app'];
let isVueJS3 = (typeof Vue.createApp == "function");

let decodedData = null

let appParams = {
    beforeMount(){
        this.importRawDataset()
    },
    data(){
        return {
            buttonForCollapse:null,
            entryId:'',
            errors:{},
            existingReceipts:[],
            loading:true,
            payments:{},
            show:false,
            showTooltip:[],
            t:{},
            token:'',
            updating:[]
        }
    },
    computed:{
        element(){
            return this.getElement()
        },
        paymentsSorted(){
            return Object.entries(this.payments).sort((a,b)=>{
                return (b[1]?.date ?? '').localeCompare(a[1]?.date ?? '')
            })
        }
    },
    methods:{
        addInArray(arrayName,value){
            if(!this[arrayName].includes(value)){
                this[arrayName].push(value)
            }
        },
        formatDate(rawDate){
            try {
                const date = new Date(rawDate)
                const day = date.getDate()
                const month = date.getMonth() + 1
                const year = date.getFullYear()
                return `${day < 10 ? 0 : ''}${day}/${month < 10 ? 0 : ''}${month}/${year}`
            } catch (error) {
                return ''
            }
        },
        formatTotal(rawTotal){
            try {
                const cents = rawTotal % 1
                return `${Math.round(rawTotal - cents)},${cents < 0.1 ? 0 : ''}${Math.round(cents*100)}`
            } catch (error) {
                return ''
            }
        },
        async getToken(force = false){
            if (this.token?.length > 0 && !force){
                return this.token
            }
            return await asyncHelper.fetch(
                window.wiki.url(`?api/hpf/receipts/token`),
                'post'
            )
            .then((data)=>{
                if (data?.token?.length > 0){
                    this.token = data.token
                    return data.token
                }
                return Promise.reject('token not generated')
            })
        },
        getElement(){
            return isVueJS3 ? this.$el.parentNode : this.$el
        },
        importDataset(){
            if (decodedData === null){
                throw new Error("'decodedData' should ot be null")
            }
            ['entryId','existingReceipts','payments','t'].forEach((key)=>{
                if (Array.isArray(decodedData[key])){
                    this[key].splice(0,this[key].length)
                    decodedData[key].forEach((e)=>this[key].push(typeof e === 'number' ? ''+e : e))
                } else if (typeof decodedData[key] === 'object' && decodedData[key] !== null){
                    this[key] = {}
                    Object.keys(decodedData[key]).forEach((k)=>{this[key][k] = decodedData[key][k]})
                } else {
                    this[key] = decodedData[key]
                }
            })
        },
        importRawDataset(){
            try {
                const rawData = this.getElement().dataset?.data
                if (rawData){
                    const decodedDataRaw = JSON.parse(rawData);
                    ['entryId','existingReceipts','payments'].forEach((key)=>{
                        if (!(key in decodedDataRaw)){
                            throw new Error(`dataset shoud contain data.${key} !`)
                        }
                    })
                    decodedData = decodedDataRaw
                }
                
                const tData = this.getElement().dataset?.t
                if (tData){
                    const decodedT = JSON.parse(tData);
                    decodedData.t = decodedT
                }
            } catch (error) {
                console.error(error)
            }
        },
        removeInArray(arrayName,value){
            if(this[arrayName].includes(value)){
                this[arrayName].splice(this[arrayName].indexOf(value),1)
            }
        },
        setEventsOnButton(){
            if (this.buttonForCollapse === null){
                const btn = this.element.parentNode.querySelector('span.BAZ_label button.receipts-collapse-button')
                if (btn){
                    this.buttonForCollapse = btn
                    btn.removeAttribute('disabled')
                    btn.addEventListener('click',()=>{
                        this.show = !this.show
                    })
                }
            }
        },
        toggleButton(show){
            if (show){
                this.buttonForCollapse.querySelector('i.show-collapsed').style.display = 'none'
                this.buttonForCollapse.querySelector('i.hide-collapsed').removeAttribute('style')
                this.updatingReceipts().catch(asyncHelper.manageError)
            } else {
                this.buttonForCollapse.querySelector('i.hide-collapsed').style.display = 'none'
                this.buttonForCollapse.querySelector('i.show-collapsed').removeAttribute('style')
            }
        },
        async updateAReceipt(){
            if (this.updating.length === 0){
                this.loading = false
                return true
            }
            this.loading = true
            const id = this.updating[0]
            /* generate receipt */
            const url = window.wiki.url(`?api/hpf/receipts/generate/${this.entryId}/${id}`)
            await this.getToken()
            .then((token)=>asyncHelper.fetch(url,'post',{token}))
            .catch(async (error)=>{
                if (error?.errorMsg === 'bad token'){
                    // redo once
                    return await this.getToken(true)
                        .then((token)=>asyncHelper.fetch(url,'post',{token}))
                }
                return Promise.reject(error)
            })
            .then((data)=>{
                if (data?.ok === true && !this.existingReceipts.includes(id)){
                    this.existingReceipts.push(id)
                }
            })
            .finally(()=>{
                this.removeInArray('updating',id)
            })
            .catch((error)=>{
                if (typeof error === 'object' && 'errorMsg' in error){
                    this.errors[id] = error.errorMsg
                } else {
                    return Promise.reject(error)
                }
            })
            return await this.updateAReceipt()
        },
        async updatingReceipts(){
            Object.keys(this.payments).forEach((id)=>{
                if (!this.existingReceipts.includes(id)
                    && !(id in this.errors)
                    && !this.updating.includes(id)){
                    this.updating.push(id)
                }
            })
            return this.loading ? false : await this.updateAReceipt()
        }
    },
    mounted(){
        $(this.element).on('dblclick',function(e) {
            return false
        })
        this.importDataset()
        this.setEventsOnButton()
        this.loading = false
    },
    watch:{
        show(show){
            this.toggleButton(show)
        }
    },
    template: `
        <div v-show="show">
            <ul>
                <li v-for="paymentData in paymentsSorted" class="receipt-payment-li">
                    <span class="payment-date">{{ formatDate(paymentData[1].date) }}</span>
                    , <span class="payment-value">{{ formatTotal(paymentData[1].total) }} €</span>
                    <button
                        :disabled="!existingReceipts.includes(paymentData[0])"
                        class="btn btn-primary btn-icon btn-xs"
                        @click="download(paymentData[0])"
                        :style="updating.includes(paymentData[0]) ? {cursor:'wait'} : false"
                        @mouseover="addInArray('showTooltip',paymentData[0])"
                        @mouseleave="removeInArray('showTooltip',paymentData[0])"
                        >
                        <i class="fa fa-download"></i>
                    </button>
                    <div v-if="showTooltip.includes(paymentData[0])"
                        class="receipt-tooltip"
                        >
                        <div class="tooltip right in">
                            <div class="tooltip-arrow" style="top: 50%;"></div>
                            <div class="tooltip-inner">
                                {{ updating.includes(paymentData[0]) ? t.updating : (
                                    existingReceipts.includes(paymentData[0])
                                        ? t.download
                                        : t.notExisting
                                    ) }}
                            </div>
                        </div>
                    </div>
                    <span v-if="paymentData[0] in errors" class="error-message">{{ errors[paymentData[0]] }}</span>
                </li>
            </ul>
        </div>
    `
};

if (isVueJS3){
  let app = Vue.createApp(appParams);
  app.config.globalProperties.wiki = wiki;
  app.config.globalProperties._t = _t;
  rootsElements.forEach(elem => {
      app.mount(elem);
  });
} else {
  Vue.prototype.wiki = wiki;
  Vue.prototype._t = _t;
  rootsElements.forEach(elem => {
      new Vue({
          ...{el:elem},
          ...appParams
      });
  });
}