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

const areas = {
    "ARA":[1,3,7,15,26,38,42,43,63,69,73,74],
    "BFC":[21,25,39,58,70,71,89,90],
    "BRE":[22,29,35,44,56],
    "CVL":[18,28,36,37,41,45],
    "COR":[20],
    "GES":[8,10,51,52,54,55,57,67,68,88],
    "HDF":[2,59,60,62,80],
    "IDF":[75,77,78,91,92,93,94,95],
    "NOR":[14,27,50,61,76],
    "NAQ":[16,17,19,23,24,33,40,47,64,79,86,87],
    "OCC":[9,11,12,30,31,32,34,46,48,65,66,81,82],
    "PDL":[44,49,53,72,85],
    "PAC":[4,5,6,13,83,84],
    "GP":[971],
    "GF":[973],
    "RE":[974],
    "MQ":[972],
    "YT":[976],
    "ETR":[99],
    "sans":[0]
}

const defaultValues = {
    1:[0,0],
    2:[0,0],
    3:[0,0],
    4:[0,0],
    donation:[0,0],
    partner:[0,0]
}

const defaultData = {}
Object.keys(areas).forEach((areaCode)=>{
    defaultData[areaCode] = {...defaultValues}
    areas[areaCode].forEach((deptNum)=>{
        defaultData[deptNum] = {...defaultValues}
    })
})

const currentYear = (new Date()).getFullYear()

let toggleHpfTableByCatAreaInternal = (areaCode) => {
    // do nothing
}

window.toggleHpfTableByCatArea = (areaCode) => {
    toggleHpfTableByCatAreaInternal(areaCode)
}

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
            areasDroppedDown:[],
            cache: {},
            columns: [],
            currentYear,
            message: null,
            messageClass: {['alert-info']:true},
            params: null,
            payments: {...defaultData},
            rows: {},
            sums:{...defaultValues},
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
            return TemplateRenderer.render('HpfPaymentsTableByCat',this,'refreshingtext')
        },
        sumtranslate(){
            return TemplateRenderer.render('HpfPaymentsTableByCat',this,'sumtranslate')
        },
        selectYear(){
            return TemplateRenderer.render('HpfPaymentsTableByCat',this,'selectyear')
        },
        title(){
            return TemplateRenderer.render('HpfPaymentsTableByCat',this,'title',{year:this.year})
        }
    },
    methods:{
        addRows(){
            Object.entries(this.payments).forEach(([id,row])=>{
                const formattedData = {}
                const isDept = (id === 0 || id === '0' || (id > 0 && id < 1000))
                const areaAssociatedKey = isDept
                ? Object.entries(areas)
                    .filter(([,v])=>v.includes(Number(id)))
                    .map(([k,])=>k)
                    ?.[0] ?? 'unknown'
                : ''
                if (!isDept || this.areasDroppedDown.includes(areaAssociatedKey)){
                    this.columns.forEach((col)=>{
                        if (!(typeof col.data === 'string')){
                            formattedData[col.data] = ''
                        } else {
                            switch (col.data) {
                                case 'name':
                                    const areaName = isDept
                                        ? ''
                                        : TemplateRenderer.render(
                                            'HpfPaymentsTableByCat',
                                            this,
                                            `areaname${id.toLocaleLowerCase()}`,
                                        )
                                    formattedData[col.data] = {
                                        display: areaName,
                                        sort: isDept ? `${areaAssociatedKey}_${(id < 10 ? '0' : '' ) +id}` : `${id}_00_${areaName}`
                                    }
                                    break
                                case 'dept':
                                    formattedData[col.data] = isDept
                                        ? TemplateRenderer.render(
                                            'HpfPaymentsTableByCat',
                                            this,
                                            'dep',
                                            {num:id}
                                        )
                                        : `<div class="btn btn-xs btn-primary" onClick="toggleHpfTableByCatArea('${id}')"><i class="fas ${this.areasDroppedDown.includes(id)
                                            ? 'fa-caret-square-up'
                                            : 'fa-caret-square-down'}"></i></div>`
                                    break
                                case 'year':
                                    formattedData[col.data] = this.getSum(row)
                                    break
                                case '#year':
                                    formattedData[col.data] = this.getSumNb(row)
                                    break
                                default:
                                    if (col.data.slice(0,1) === '#'){
                                        formattedData[col.data] = row?.[col.data.slice(1)]?.[1] ?? ''
                                    } else {
                                        formattedData[col.data] = row?.[col.data]?.[0] ?? ''
                                    }
                                    break
                            }
                        }
                    })
                    this.$set(this.rows,id,formattedData)
                }
            })
        },
        appendMessage(message){
            this.message = ((this.message.length === 0)
                ? ''
                : `${this.message}<br>`)+message
        },
        displayCache(data){
            this.messageClass = {['alert-success']:true}
            this.message = TemplateRenderer.render('HpfPaymentsTableByCat',this,'cachedisplayed',{},[['{date}',data.date]])
            Object.keys(defaultData).forEach((key)=>{
                Object.keys(defaultValues).forEach((college)=>{
                    const item = data?.values?.[key]?.[college === 'donation' ? 'd' : (college === 'partner' ? 'p' : college)]
                    this.payments[key][college] = [item?.[0] ?? 0,item?.[1] ?? 0]
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
        getColumnForPayment(key,isNumber=false){
            return {
                ...{
                    data: (isNumber ? '#' :'')+key,
                    title: !isNumber
                    ? TemplateRenderer.render('HpfPaymentsTableByCat',this,(key > 0 && key <=4 )
                        ? 'name'
                        : key,
                        {},
                        [['{id}',key]]
                    )
                    :'#',
                    footer: this.sums?.[key]?.[isNumber ? 1 : 0] !== null ? `<th>${this.sums[key][isNumber ? 1 : 0]}</th>`: '',
                    render: (data,type,row)=>{
                        return (type === 'display' && !isNumber)
                            ? `${data} €`
                            : data
                    },
                },
                // ...width
            }
        },
        getColumns(){
            if (this.columns.length == 0){
                const data = {columns:[]}
                const defaultcolumnwidth = '100px';
                const width = defaultcolumnwidth.length > 0 ? {width:defaultcolumnwidth}: {}
                data.columns.push({
                    ...{
                        data: 'name',
                        title: TemplateRenderer.render('HpfPaymentsTableByCat',this,'area'),
                        footer: this.sumtranslate,
                        render: (data,type,row)=>{
                            switch (type) {
                                case 'sort':
                                    return data?.sort ?? data?.display ?? (typeof data === 'string' ? data : '')
                                default:
                                    return data?.display ?? (typeof data === 'string' ? data : '')
                            }
                        }
                    },
                    ...width
                })
                data.columns.push({
                    ...{
                        data: 'dept',
                        title: TemplateRenderer.render('HpfPaymentsTableByCat',this,'dept'),
                        footer: `<th>${this.sumtranslate}</th>`,
                        sortable:false
                    },
                    ...width
                })
                const bigSum = Object.values(this.sums)
                    .reduce((sum,value)=>sum += value?.[0] ?? 0,0)
                const bigSumNb = Object.values(this.sums)
                    .reduce((sum,value)=>sum += value?.[1] ?? 0,0)
                data.columns.push({
                    ...{
                        data: 'year',
                        title: TemplateRenderer.render('HpfPaymentsTableByCat',this,'yeartotal'),
                        footer: `<th>${bigSum}</th>`,
                        render: (data,type,row)=>{
                            switch (type) {
                                case 'sort':
                                    return data
                                default:
                                    return data + ' €'
                            }
                        }
                    },
                    ...width
                })
                data.columns.push({
                        data: '#year',
                        title: '#',
                        footer: `<th>${bigSumNb}</th>`,
                    }
                )
                Object.keys(defaultValues).forEach((key)=>{
                    data.columns.push(this.getColumnForPayment(key,false))
                    data.columns.push(this.getColumnForPayment(key,true))
                })
                this.columns = data.columns
            }
            return this.columns
        },
        getSum(row){
            return Object.entries(row)
                .filter(([key,])=>!['name','dept','year'].includes(key))
                .reduce((sum,[,value])=>sum += value[0],0)
        },
        getSumNb(row){
            return Object.entries(row)
                .filter(([key,])=>!['name','dept','year'].includes(key))
                .reduce((sum,[,value])=>sum += value[1],0)
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
            this.appendMessage(TemplateRenderer.render('HpfPaymentsTableByCat',this,'gettingcache',{},[['{year}',year]]))
            return await this.fetchJsonSecure(wiki.url(`?api/triples/${year}&property=https://www.habitatparticipatif-france.fr/PaymentsCacheByCat`))
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
                        this.appendMessage(`<b>${TemplateRenderer.render('HpfPaymentsTableByCat',this,'error')}</b>`)
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
                this.message = TemplateRenderer.render('HpfPaymentsTableByCat',this,'refresh')
            } else {
                this.appendMessage(TemplateRenderer.render('HpfPaymentsTableByCat',this,'refresh'))
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
            return await this.fetchJsonSecure('?api/hpf/payments-by-cat/refreshcache',options,false)
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
        async toggleHpfTableByCatArea(areaCode){
            if (this.areasDroppedDown.includes(areaCode)){
                const newT = this.areasDroppedDown.filter((e)=>e!=areaCode)
                this.areasDroppedDown = newT
            } else {
                this.areasDroppedDown.push(areaCode)
            }
            this.removeRows()
            this.$nextTick(()=>{
                this.addRows()
                this.toggleRefresh = !this.toggleRefresh
            })
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
                        this.message = TemplateRenderer.render('HpfPaymentsTableByCat',this,'nocache',{},[['{year}',newYear]])
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
                    this.appendMessage(`<b>${TemplateRenderer.render('HpfPaymentsTableByCat',this,'error')}</b>`)
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
        toggleHpfTableByCatAreaInternal = this.toggleHpfTableByCatArea
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
              :title="refreshingText"
              :disabled="isLoading"
              @click="refresh">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        <div :class="{...{alert:true},...(message ? messageClass : {['alert-info']:true})}">
            <span v-if="message" v-html="message"></span>
            <br v-else>
        </div>
        <dyn-table :columns="columns" :rows="rows" :forceRefresh="toggleRefresh" :uuid="getUuid()" :forceDisplayTotal="true">
            <template #dom>&lt;'row'&lt;'col-sm-12'tr>>&lt;'row'&lt;'col-sm-6'i>&lt;'col-sm-6'&lt;'pull-right'B>>></template>
            <template #sumtranslate>{{ sumtranslate }}</template>
        </dyn-table>
    </div>
    `
}