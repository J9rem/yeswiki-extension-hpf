/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import DynTable from 'DynTable'
import TemplateRenderer from 'TemplateRenderer'

const isVueJS3 = (typeof window.Vue.createApp == "function");

const defaultValues = {}
for (let index = 1; index <= 12; index++) {
    defaultValues[`${index}`] = 0
}
defaultValues.other = 0

const defaultData = {}
for (let index = 1; index <= 5; index++) {
    defaultData[`${index}`] = {...defaultValues}
}
defaultData.donation = {...defaultValues}

const currentYear = (new Date()).getFullYear()

class NoCacheError extends Error {}

export default {
    model: {
        prop: 'isLoading',
        event: 'update-loading'
    },
    components: {DynTable},
    props: ['isLoading'],
    data: function() {
        return {
            cache: {},
            columns: [],
            currentYear,
            message: null,
            messageClass: {['alert-info']:true},
            params: null,
            payments: {...defaultData},
            rows: {},
            toggleRefresh: false,
            token: '',
            uuid: null,
            year: currentYear
        }
    },
    computed:{
        element(){
            return isVueJS3 ? this.$el.parentNode : this.$el
        },
        refreshingText(){
            return TemplateRenderer.render('HpfPaymentsTable',this,'refreshingtext')
        },
        sumtranslate(){
            return TemplateRenderer.render('HpfPaymentsTable',this,'sumtranslate')
        },
        selectYear(){
            return TemplateRenderer.render('HpfPaymentsTable',this,'selectyear')
        },
        title(){
            return TemplateRenderer.render('HpfPaymentsTable',this,'title',{year:this.year})
        }
    },
    methods:{
        addRows(){
            Object.entries(this.payments).forEach(([id,row])=>{
                const formattedData = {}
                this.columns.forEach((col)=>{
                    if (!(typeof col.data === 'string')){
                        formattedData[col.data] = ''
                    } else {
                        switch (col.data) {
                            case 'name':
                                formattedData[col.data] = TemplateRenderer.render(
                                        'HpfPaymentsTable',
                                        this,
                                        (id === 'donation')
                                            ? 'donation' 
                                            : (id == '5'
                                                ? 'partner'
                                                : 'name'
                                            ),
                                        {},
                                        [['{id}',id]]
                                    )
                                break
                            case 'year':
                                formattedData[col.data] = this.getSum(row)
                                break
                            default:
                                formattedData[col.data] = row?.[col.data] ?? ''
                                break
                        }
                    }
                })
                this.$set(this.rows,id,formattedData)
            })
        },
        appendMessage(message){
            this.message = ((this.message.length === 0)
                ? ''
                : `${this.message}<br>`)+message
        },
        displayCache(data){
            this.messageClass = {['alert-success']:true}
            this.message = TemplateRenderer.render('HpfPaymentsTable',this,'cachedisplayed',{},[['{date}',data.date]])
            Object.keys(defaultData).forEach((key)=>{
                Object.keys(defaultValues).forEach((month)=>{
                    this.payments[key][month] = data?.values?.[key === 'donation' ? 'd' : key]?.[month === 'other' ? 'o' : month] ?? 0
                })
            })
        },
        async fetchJsonSecure(url,options={},returnNullOnError=true){
            return await fetch(url,options)
                .then((response)=>{
                    if (response.ok){
                        return response.json()
                    }
                    throw new Error(`response badly formatted (${response.status} - ${response.statusText})`)
                })
                .catch((error)=>{
                    this.manageError(error)
                    return returnNullOnError ? null : Promise.reject(error)
                })
        },
        getColumns(){
            if (this.columns.length == 0){
                const data = {columns:[]}
                const defaultcolumnwidth = '100px';
                const width = defaultcolumnwidth.length > 0 ? {width:defaultcolumnwidth}: {}
                data.columns.push({
                    ...{
                        data: 'name',
                        title: TemplateRenderer.render('HpfPaymentsTable',this,'firstcolumntitle'),
                        footer: ''
                    },
                    ...width
                })
                data.columns.push({
                    ...{
                        data: 'year',
                        class: 'sum-activated',
                        title: TemplateRenderer.render('HpfPaymentsTable',this,'yeartotal'),
                        footer: ''
                    },
                    ...width
                })
                Object.keys(defaultValues).forEach((key)=>{
                    const associations = {
                        '1':'jan',
                        '2':'feb',
                        '3':'mar',
                        '4':'apr',
                        '5':'may',
                        '6':'jun',
                        '7':'jul',
                        '8':'aug',
                        '9':'sep',
                        '10':'oct',
                        '11':'nov',
                        '12':'dec',
                    }
                    data.columns.push({
                        ...{
                            data: key,
                            class: 'sum-activated',
                            title: TemplateRenderer.render('HpfPaymentsTable',this,associations?.[key] ?? key),
                            footer: ''
                        },
                        // ...width
                    })
                })
                this.columns = data.columns
            }
            return this.columns
        },
        getSum(row){
            return Object.entries(row)
                .filter(([key,])=>{
                    return (key === 'other' 
                        || (
                            String(Number(key)) == String(key)
                            && Number(key) > 0
                            && Number(key) < 13
                        ))
                })
                .reduce((sum,[,value])=>sum += value,0)
        },
        getUuid(){
            if (this.uuid === null){
                this.uuid = crypto.randomUUID()
            }
            return this.uuid
        },
        async loadCache(year){
            if (year in this.cache){
                return this.cache[year]
            }
            this.messageClass = {['alert-info']:true}
            this.appendMessage(TemplateRenderer.render('HpfPaymentsTable',this,'gettingcache',{},[['{year}',year]]))
            return await this.fetchJsonSecure(wiki.url(`?api/triples/${year}&property=https://www.habitatparticipatif-france.fr/PaymentsCache`))
                .then((data)=>{
                    if (!Array.isArray(data)
                        || data.length === 0
                        || typeof data[0] !== 'object'
                        || String(data[0]?.resource) !== String(year)
                        || (typeof data[0]?.value !=='string')){
                        throw new NoCacheError('Pas de cache')
                    }
                    const values = JSON.parse(data[0].value)
                    if ((typeof values !== 'object') ||!('date' in values)||!('values' in values)){
                        throw new NoCacheError('Mal formatté')
                    }
                    this.cache[year] = values
                    return this.cache[year]
                })
        },
        manageError(error = null){
            if (error && wiki.isDebugEnabled){
                console.error(error)
            }
            return null
        },
        async refresh(){
            if (!this.isLoading){
                this.$emit('update-loading',true)
                return await this.refreshCache()
                    .then(()=>{
                        return this.loadCache(this.year)
                    })
                    .then(this.displayCache)
                    .catch((error)=>{
                        this.messageClass = {['alert-danger']:true}
                        this.appendMessage(`<b>${TemplateRenderer.render('HpfPaymentsTable',this,'error')}</b>`)
                        this.manageError(error)
                        return null
                    })
                    .finally(()=>{
                        if (this.isLoading){
                            this.$emit('update-loading',false)
                        }
                    })
            }
        },
        async refreshCache(preserveClass = false){
            if (!preserveClass){
                this.messageClass = {['alert-info']:true}
                this.message = TemplateRenderer.render('HpfPaymentsTable',this,'refresh')
            } else {
                this.appendMessage(TemplateRenderer.render('HpfPaymentsTable',this,'refresh'))
            }
            Object.keys(this.cache).forEach((year)=>{
                this.$delete(this.cache,year)
            })
            let formData = new FormData()
            formData.append('anti-csrf-token',this.token)
            formData.append('formsIds[1]',this.params?.forms?.[1] ?? '')
            formData.append('formsIds[2]',this.params?.forms?.[2] ?? '')
            formData.append('formsIds[3]',this.params?.forms?.[3] ?? '')
            formData.append('formsIds[4]',this.params?.forms?.[4] ?? '')
            formData.append('formsIds[5]',this.params?.forms?.partner ?? '')
            formData.append('college3to4fieldname',this.params?.college3to4fieldname ?? '')
            const options = {
                method: 'POST',
                body: new URLSearchParams(formData),
                headers: (new Headers()).append('Content-Type','application/x-www-form-urlencoded')
            }
            return await this.fetchJsonSecure('?api/hpf/helloasso/payment/refreshcache',options,false)
                .then((data)=>{
                    if(typeof data?.newtoken === 'string'){
                        this.token = data.newtoken
                    }
                })
        },
        removeRows(){
            Object.keys(this.rows).forEach((id)=>{
                this.$delete(this.rows,id)
            })
        },
        async secureResetIsloading(){
            setTimeout(()=>{
                if (this.isLoading){
                    this.$emit('update-loading',false)
                }
            },5000)
        },
        updatePayments(){
            this.getColumns()
            this.removeRows()
            this.addRows()
            this.toggleRefresh = !this.toggleRefresh
        },
        async updateYear(newYear){
            this.$emit('update-loading',true)
            this.message = ''
            await this.loadCache(newYear)
                .catch(async (error)=>{
                    if (error instanceof NoCacheError){
                        this.messageClass = {['alert-warning']:true}
                        this.message = TemplateRenderer.render('HpfPaymentsTable',this,'nocache',{},[['{year}',newYear]])
                        return await this.refreshCache(true)
                            .then(()=>{
                                return this.loadCache(newYear)
                            })
                    }
                    return Promise.reject(error)
                })
                .then(this.displayCache)
                .catch((error)=>{
                    this.messageClass = {['alert-danger']:true}
                    this.appendMessage(`<b>${TemplateRenderer.render('HpfPaymentsTable',this,'error')}</b>`)
                    this.manageError(error)
                    return null
                })
                .finally(()=>{
                    if (this.isLoading){
                        this.$emit('update-loading',false)
                    }
                })
        }
    },
    mounted(){
        $(this.element).on('dblclick',function(e) {
            return false;
        });
        const rawParams = this.element.dataset?.params
        if (rawParams){
            try {
                this.params = JSON.parse(rawParams)
                this.token = this.params?.['anti-csrf-token'] ?? ''
            } catch (error) {
                console.error(error)
            }
        }
        this.updatePayments() // to force display table
        this.updateYear(this.year).catch(this.manageError)
    },
    watch:{
        payments:{
            deep: true,
            handler(n){
                this.updatePayments()
            }
        },
        year(newYear){
            this.updateYear(newYear)
        }
    },
    template: `
    <div>
        <h2>{{ title }}</h2>
        <div>
            <i>{{ selectYear }}</i>
            <select name="undefined-name" v-model="year" :disabled="isLoading">
                <template v-for="y in (currentYear - 2022 + 2)">
                    <option :value="2021 +y">{{ 2021 + y }}</option>
                </template>
            </select>
            <button
              class="btn btn-xs btn-primary"
              title="refreshingText"
              :disabled="isLoading"
              @click="refresh">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        <div :class="{...{alert:true},...(message ? messageClass : {['alert-info']:true})}">
            <span v-if="message" v-html="message"></span>
            <br v-else>
        </div>
        <dyn-table :columns="columns" :rows="rows" :forceRefresh="toggleRefresh">
            <template #dom>&lt;'row'&lt;'col-sm-12'tr>>&lt;'row'&lt;'col-sm-6'i>&lt;'col-sm-6'&lt;'pull-right'B>>></template>
            <template #sumtranslate>{{ sumtranslate }}</template>
        </dyn-table>
    </div>
    `
}