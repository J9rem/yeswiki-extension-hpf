/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-register-payment-action
 */

// import HpfTable from './components/HpfTable.js'
import SpinnerLoader from '../../bazar/presentation/javascripts/components/SpinnerLoader.js'

const rootsElements = ['.dynamic-hpf-register-payment-action'];
const isVueJS3 = (typeof window.Vue.createApp == "function");

const computedDebounce = (name) => {
    return {
        get(){
            return this.search?.[name] ?? ''
        },
        set(val){
            this.pendingsearch[name] = (val.length === 0) ? '' : val
            if(this.timeout?.[name]){
                clearTimeout(this.timeout[name])
            }
            this.timeout[name] = setTimeout(
                ()=>{
                    this.search[name] = this.pendingsearch[name]
                    this.timeout[name] = null
                },
                500 // bounce time
            )
        }
    }
}

let appParams = {
    components: {vuejsDatepicker,SpinnerLoader},
    data(){
        return {
            cacheEntries:{},
            cacheForId: {
                type:'',
                date:'',
                previous:''
            },
            cacheResolveReject:{},
            cacheSearch:{},
            canUseId: false,
            currentResults: {},
            datePickerLang: vdp_translation_index,
            datePickerLanguage: null,
            formsids: [],
            isLoading: false,
            newPayment:{
                date:'',
                total: 0,
                origin: 'virement',
                id: '',
                helloassoId: '',
                year: ''
            },
            notSearching: true,
            notSearchingHelloAsso: true,
            params: null,
            pendingsearch: {
                email: '',
                firstName: '',
                name: '',
                amount:''
            },
            refreshing:false,
            search: {
                email: '',
                firstName: '',
                name: '',
                amount:''
            },
            selectedEntryId: '',
            selectedForm: '',
            timeout: {
                email: null,
                firstName: null,
                name: null
            }
        }
    },
    computed:{
        availableIds(){
            const id = this.getCurrentIdForHA.id
            return (!(id in this.cacheSearch))
                ? 'erreur'
                : this.convertPaymentsToOptions(this.cacheSearch?.[id])
        },
        canAddPayment(){
            this.updateCanUseId()
            return this.selectedEntryId?.length > 0
                && Number(this.newPayment.total) > 0
                && this.newPayment.date?.length > 0
                && this.newPayment.origin?.length > 0
                && this.currentWantedId?.length > 0
                && this.canUseId
        },
        currentWantedId(){
            return this.newPayment.origin !== 'helloasso'
                ? String(this.newPayment.id)
                : String(this.newPayment.helloassoId)
        },
        element(){
            return isVueJS3 ? this.$el.parentNode : this.$el
        },
        getCurrentIdForHA(){
            const date = this.convertDateFromFormat(this.newPayment.date)
            const amount = this.newPayment.total
            const res = {id:null,formattedDate:''}
            if (!date || date?.length === 0){
                return res
            }
            const intermDate = new Date(date)
            if (!intermDate){
                return res
            }
            const formattedDate = intermDate.toJSON()?.slice(0,10)
            if (!formattedDate){
                return res
            }
            return {
                id:`getHelloAssoIds${formattedDate}${String(amount)}`,
                formattedDate
            }
        },
        searchedEmail: computedDebounce.call(this,'email'),
        searchedFirstName: computedDebounce.call(this,'firstName'),
        searchedName: computedDebounce.call(this,'name'),
        searchedAmount: computedDebounce.call(this,'amount')
    },
    methods:{
        async addNewPayment(){
            if (!this.refreshing && this.canAddPayment){
                this.refreshing = true
                await this.fetchSecured(wiki.url('?api/hpf/helloasso/payment/getToken'),{method:'POST'})
                    .then(async (token)=>{
                        let formData = new FormData()
                        formData.append('anti-csrf-token',token)
                        formData.append('id',this.currentWantedId)
                        formData.append('date',this.convertDateFromFormat(this.newPayment.date))
                        formData.append('origin',this.newPayment.origin)
                        formData.append('total',this.newPayment.total)
                        formData.append('year',this.newPayment.year)
                        return await this.fetchSecured(
                            wiki.url(`?api/hpf/helloasso/payment/${this.selectedEntryId}/add`),
                            {
                                method:'POST',
                                body: new URLSearchParams(formData),
                                headers: (new Headers()).append('Content-Type','application/x-www-form-urlencoded')
                            }
                        )
                    })
                    .then((data)=>{
                        if (data?.status === 'ok'){
                            const updatedEntry = data?.updatedEntry
                            if (updatedEntry?.id_fiche?.length > 0){
                                this.newPayment.total = 0
                                this.$set(this.cacheEntries,updatedEntry.id_fiche,updatedEntry)
                                const saveSelectedEntryId = this.selectedEntryId
                                this.selectedEntryId = ''
                                this.$nextTick(()=>{
                                    this.selectedEntryId = saveSelectedEntryId
                                })
                            }
                        }
                    })
                    .catch(this.manageError)
                    .finally(()=>{
                        this.refreshing = false
                    })
            }
        },
        convertDateFromFormat(date){
            return `${date.slice(6,10)}-${date.slice(0,2)}-${date.slice(3,5)}`
        },
        customFormatterDate(date,type = ''){
          const dd = (!date || date?.length == 0) ? new Date() : new Date(date)
          if (Date().toString() === 'Invalid Date'){
            this.manageError(new Error(`Date (${date}) is not seen as a date !`))
            return ''
          }
          let day = dd.getDate()
          if (day < 10){
            day = `0${day}`
          }
          let month = dd.getMonth()+1
          if (month < 10){
            month = `0${month}`
          }
          const year = dd.getFullYear()
          return type === 'fr' ? `${day}/${month}/${year}` : `${month}/${day}/${year}`
        },
        convertPaymentsToOptions(raw){
            return Object.fromEntries(Object.values(raw?.payments ?? {}).map((payment)=>{
                return [payment?.id ?? 'unknown-id',`${payment?.id} (${payment?.payer?.email} - ${payment?.payer?.firstName} ${payment?.payer?.lastName})`]
            }) ?? [])
        },
        async deletePayment(entryId,paymentId){
            if (!this.refreshing && entryId?.length > 0 && paymentId?.length > 0){
                this.refreshing = true
                await this.fetchSecured(wiki.url('?api/hpf/helloasso/payment/getToken'),{method:'POST'})
                    .then(async (token)=>{
                        let formData = new FormData()
                        formData.append('anti-csrf-token',token)
                        return await this.fetchSecured(
                            wiki.url(`?api/hpf/helloasso/payment/${entryId}/delete/${paymentId}`),
                            {
                                method:'POST',
                                body: new URLSearchParams(formData),
                                headers: (new Headers()).append('Content-Type','application/x-www-form-urlencoded')
                            }
                        )
                    })
                    .then((data)=>{
                        if (data?.status === 'ok'){
                            const updatedEntry = data?.updatedEntry
                            if (updatedEntry?.id_fiche?.length > 0){
                                this.newPayment.total = 0
                                this.$set(this.cacheEntries,updatedEntry.id_fiche,updatedEntry)
                                const saveSelectedEntryId = this.selectedEntryId
                                this.selectedEntryId = ''
                                this.$nextTick(()=>{
                                    this.selectedEntryId = saveSelectedEntryId
                                    this.updateCanUseId()
                                })
                                this.updateCanUseId()
                            }
                        }
                    })
                    .catch(this.manageError)
                    .finally(()=>{
                        this.refreshing = false
                    })
            }
        },
        extractValue(entry,name){
            if (typeof entry !== 'object' || Object.keys(entry) === 0){
                return ''
            }
            if (name in entry){
                return entry[name]
            }
            return Object.entries(entry)
                .filter(([fieldName,])=>fieldName.slice(-name.length) === name)
                .map(([fieldName,value])=>value)
                ?.[0] ?? ''
        },
        extractPayments(entry){
            if (typeof entry !== 'object' || Object.keys(entry) === 0){
                return {}
            }
            const raw = this.extractValue(entry,'bf_payments')
            if (raw?.length > 0){
                const decoded = JSON.parse(raw)
                if (typeof decoded === 'object' && decoded !== null){
                    return decoded
                }
            }
            return {}
        },
        async fetchSecured(url,options={}){
            return await fetch(url,options)
                .then((response)=>{
                    if(!response.ok){
                        throw new Error(`Response badly formatted (${response.status} - ${response.statusText})`)
                    }
                    return response.json()
                })
        },
        async getPayments(formattedDate, amount, token){
            let formData = new FormData()
            formData.append('anti-csrf-token',token)
            const url = wiki.url(`?api/hpf/helloasso/payment/find/${formattedDate}/${String(Math.round(Number(amount)*100))}`)
            const options = {
                method:'POST',
                body: new URLSearchParams(formData),
                headers: (new Headers()).append('Content-Type','application/x-www-form-urlencoded')
            }
            return await this.fetchSecured(url,options)
        },
        getQueryForName(data){
            const query = {}
            if ('firstName' in data){
                query.bf_prenom = '.*'+data.firstName+'.*'
            }
            if ('name' in data){
                query.bf_nom = '.*'+data.name+'.*'
            }
            if ('email' in data){
                query.bf_mail = '.*'+data.email+'.*'
            }
            if ('amount' in data){
                query.bf_calc = data.amount
            }
            return query
        },
        getQueryForTitle(data){
            const query = {}
            if ('name' in data){
                query.bf_titre = '.*'+data.name+'.*'
            }
            if ('email' in data){
                query.bf_mail = '.*'+data.email+'.*'
            }
            if ('amount' in data){
                query.bf_calc = data.amount
            }
            return query
        },
        async getResultsForQuery(query){
            const formattedQuery = Object.entries(query).map(([key,value])=>`${key}=${value}`).join('|')
            if (formattedQuery.length ===0){
                return {}
            }
            const id = ''+ (this.selectedForm.length > 0 ? this.selectedForm : 'allforms') + formattedQuery;
            return await this.waitFor('notSearching')
                .then(async ()=>{
                    this.notSearching = false
                    return await this.waitForCache(
                            id,
                            async () => {
                                const url = this.selectedForm.length > 0
                                    ? wiki.url(`?api/forms/${this.selectedForm}/entries&query=${formattedQuery}`)
                                    : wiki.url(`?api/entries&query=${formattedQuery}|id_typeannonce=${this.formsids.join(',')}`)
                                return await this.fetchSecured(url)
                                .catch((error)=>{
                                    this.notSearching = true
                                    return Promise.reject(error)
                                })
                            }
                        )
                        .finally(()=>{
                            this.notSearching = true
                        })
                })
        },
        async getToken(){
            return await this.fetchSecured(wiki.url('?api/hpf/helloasso/payment/getToken'),{method:'POST'})
        },
        hasResults(results){
            return typeof results === 'object' && results !== null && Object.keys(results).length > 0
        },
        manageError(error){
            if (wiki.isDebugEnabled){
                console.error(error)
            }
            return null
        },
        reloadPage(){
            window.location.reload()
        },
        refreshAvailableIds(){
            if (this.newPayment.origin === 'helloasso'
                && this.newPayment.date?.length > 0
                && Number(this.newPayment.total) > 0){
                // refresh
                this.refreshHelloAssoIds()
                    .catch(this.manageError)
            }
        },
        async refreshHelloAssoIds(previousId = '', previousformattedDate = ''){
            const amount = this.newPayment.total
            let id = ''
            let formattedDate = ''
            if (previousId.length > 0 && previousformattedDate.length > 0){
                id = previousId
                formattedDate = previousformattedDate
            } else {
                ({id,formattedDate} = this.getCurrentIdForHA)
            }
            if (!id){
                return
            }
            return this.waitFor('notSearchingHelloAsso')
                .then(async ()=>{
                    this.notSearchingHelloAsso = false
                    return await this.waitForCache(
                        id,
                        async () => {
                            return await this.getToken()
                            .then(async (token)=>{
                                return await this.getPayments(formattedDate, amount, token)
                                .catch(async (error)=>{
                                    const promise = new Promise((resolve)=>{
                                        setTimeout(() => {
                                            resolve()
                                        },3000)
                                    })
                                    return promise.then(async ()=>{
                                        return await this.getToken()
                                            .then(async (token)=>{
                                                return await this.getPayments(formattedDate, amount, token)
                                            })
                                            .finally(()=>{
                                                this.notSearchingHelloAsso = true
                                            })
                                    })
                                })
                            })
                        }
                    )
                    .finally(()=>{
                        this.notSearchingHelloAsso = true
                    })
                })
        },
        registerPromise(id){
            if (!(id in this.cacheResolveReject)){
                this.cacheResolveReject[id] = []
            }
            const promise = new Promise((resolve,reject)=>{
                this.cacheResolveReject[id].push({resolve,reject})
            })
            return promise
        },
        resolve(name,results = null, souldReject = false){
            if (Array.isArray(this.cacheResolveReject?.[name])){
                const listOfResolveReject = this.cacheResolveReject[name]
                this.cacheResolveReject[name] = []
                listOfResolveReject.forEach(({resolve,reject})=>{
                    if (souldReject) {
                        reject(results === null ? 'error' : results)
                    } else {
                        resolve(results === null ? this?.[name] : results)
                    }
                })
            }
        },
        async searchEntry(){
            this.selectedEntryId = ''
            const data = {}
            if (this.search.firstName.length>0){
                data.firstName = this.search.firstName
            }
            if (this.search.name.length>0){
                data.name = this.search.name
            }
            if (this.search.email.length>0){
                data.email = this.search.email
            }
            if (Number(this.search.amount)>0){
                data.amount = String(this.search.amount)
            }
            await this.getResultsForQuery(this.getQueryForName(data))
                .then(async (results)=>{
                    if (this.hasResults(results)){
                        return results
                    }
                    return await this.getResultsForQuery(this.getQueryForTitle(data))
                })
                .then((results)=>{
                    if (this.hasResults(results)){
                        Object.values(results).forEach((entry)=>{
                            if (entry?.id_fiche?.length > 0 && !(entry.id_fiche in this.cacheEntries)){
                                this.$set(this.cacheEntries,entry.id_fiche,entry)
                                this.updateCanUseId()
                            }
                        })
                        this.currentResults = results
                    } else {
                        this.currentResults = {}
                    }
                })
        },
        selectEntry(id){
            if (!this.refreshing){
                this.selectedEntryId = (this.selectedEntryId == id) ? '' : id
            }
        },
        updateCanUseId(){
            if (this.currentWantedId.length > 0
                && this.selectedEntryId?.length > 0){
                const paymentsIds = Object.keys(this.extractPayments(this.cacheEntries?.[this.selectedEntryId]))
                if (paymentsIds.length > 0){
                    this.canUseId = !(paymentsIds.includes(this.currentWantedId))
                } else {
                    this.canUseId = true
                }
                return
            }
            this.canUseId = false
        },
        updatePrefilledId(){
            if(this.newPayment.origin.length > 0
                && this.newPayment.origin !== 'helloasso'
                && this.newPayment.date?.length > 0){
                if (this.newPayment.id.length === 0
                    || (
                        this.newPayment.id === this.cacheForId.previous
                        && (
                            this.newPayment.origin !== this.cacheForId.type
                            || this.newPayment.date !== this.cacheForId.date
                        )
                    )){
                    const newId = `${this.newPayment.origin}-${this.newPayment.date.replace(/\//g,'')}`
                    this.newPayment.id = newId
                    this.cacheForId.type = this.newPayment.origin
                    this.cacheForId.date = this.newPayment.date
                    this.cacheForId.previous = newId
                }
            }
        },
        updatePrefilledValue(){
            if(this.selectedEntryId?.length > 0
                && this.selectedEntryId in this.cacheEntries
                && this.newPayment.total == 0
                && this.cacheEntries[this.selectedEntryId]?.bf_calc > 0){
                this.newPayment.total = String(this.cacheEntries[this.selectedEntryId]?.bf_calc)
            }
        },
        async waitFor(name){
            if (this?.[name]){
                return true
            }
            if (!(name in this.cacheResolveReject)){
                this.cacheResolveReject[name] = []
            }
            const promise = new Promise((resolve,reject)=>{
                this.cacheResolveReject[name].push({resolve,reject})
            })
            return await promise.then((...args)=>Promise.resolve(...args)) // force .then()
        },
        async waitForCache(name,action){
            if (name in this.cacheSearch){
                return this.cacheSearch[name]
            }

            const id = `cacheSearch${name}`
            if (!(id in this.cacheResolveReject)){
                this.cacheResolveReject[id] = []
                return await action().then((results)=>{
                    this.$set(this.cacheSearch,name,results)
                    this.resolve(id,results)
                    return results
                }).catch((error)=>{
                    try {
                        this.resolve(id,error,true)
                    } catch (e) {
                        
                    }
                    return Promise.reject(error)
                })
            }
            const promise = new Promise((resolve,reject)=>{
                this.cacheResolveReject[id].push({resolve,reject})
            })
            return await promise.then((...args)=>Promise.resolve(...args)) // force .then()
        }
    },
    mounted(){
        $(this.element).on('dblclick',function(e) {
            return false
        })
        this.datePickerLanguage = (wiki.locale in this.datePickerLang)
          ? this.datePickerLang[wiki.locale]
          : this.datePickerLang.en
        const rawParams = this.element?.dataset?.params
        if (rawParams){
            try {
                this.params = JSON.parse(rawParams)
                if (this.params?.formsids){
                    this.formsids = Object.keys(this.params?.formsids)
                    if (Object.keys(this.params?.formsids).length === 1){
                        this.selectedForm = Object.keys(this.params.formsids)[0]
                    }
                }
                this.newPayment.date = this.customFormatterDate()
            } catch (error) {
                console.error(error)
            }
        }
    },
    watch:{
        selectedEntryId(){
            this.updatePrefilledValue()
            this.updateCanUseId()
        },
        notSearching(){
            this.resolve('notSearching')
        },
        notSearchingHelloAsso(){
            this.resolve('notSearchingHelloAsso')
        },
        newPayment:{
            deep: true,
            handler(value){
                if (value.origin === 'helloasso' && value.date?.length > 0 && Number(value.total) > 0){
                    // refresh
                    this.refreshHelloAssoIds()
                        .catch(this.manageError)
                }
                this.updatePrefilledId()
                this.updateCanUseId()
            }
        },
        search:{
            deep: true,
            handler(){
                this.searchEntry().catch(this.manageError)
            }
        },
        selectedForm(){
            this.searchEntry().catch(this.manageError)
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