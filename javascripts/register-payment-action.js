/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
            if(this.refreshing){
                return // do nothing
            }
            if(this.timeout?.[name]){
                clearTimeout(this.timeout[name])
            }
            if (val.length === 0){
                this.search[name] = ''
            } else {
                this.timeout[name] = setTimeout(
                    ()=>{
                        this.search[name] = val
                    },
                    500 // bounce time
                )
            }
        }
    }
}

let appParams = {
    components: {vuejsDatepicker,SpinnerLoader},
    data(){
        return {
            cacheEntries:{},
            cacheResolveReject:{},
            cacheSearch:{},
            currentResults: {},
            datePickerLang: vdp_translation_index,
            datePickerLanguage: null,
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
            refreshing:false,
            search: {
                email: '',
                firstName: '',
                name: ''
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
            return this.convertPaymentsToOptions(this.cacheSearch?.[id])
        },
        canAddPayment(){
            return this.selectedEntryId?.length > 0
                && Number(this.newPayment.total) > 0
                && this.newPayment.date?.length > 0
                && this.newPayment.origin?.length > 0
                && this.currentWantedId?.length > 0
                && this.canUseId
        },
        canUseId(){
            return this.newPayment.id?.length > 0
                && this.selectedEntryId?.length > 0
                && !Object.keys(this.extractPayments(this.cacheEntries?.[this.selectedEntryId]))
                    .includes(this.currentWantedId)
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
        searchedName: computedDebounce.call(this,'name')
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
                                this.cacheEntries[updatedEntry.id_fiche] = updatedEntry
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
          const dd = (new Date(date))
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
                                this.$set(this.cacheEntriesupdatedEntry.id_fiche,updatedEntry)
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
            return query
        },
        async getResultsForQuery(query){
            const formattedQuery = Object.entries(query).map(([key,value])=>`${key}=${value}`).join('|')
            if (formattedQuery.length ===0){
                return {}
            }
            const id = ''+ this.selectedForm + formattedQuery;
            return await this.waitFor('notSearching')
                .then(async ()=>{
                    this.notSearching = false
                    return await this.waitForCache(
                            id,
                            async () => {
                                return await this.fetchSecured(wiki.url(`?api/forms/${this.selectedForm}/entries&query=${formattedQuery}`))
                            }
                        )
                        .finally(()=>{
                            this.notSearching = true
                        })
                })
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
        async refreshHelloAssoIds(){
            const amount = this.newPayment.total
            const {id,formattedDate} = this.getCurrentIdForHA
            if (!id){
                return
            }
            return this.waitFor('notSearchingHelloAsso')
                .then(async ()=>{
                    this.notSearchingHelloAsso = false
                    return await this.waitForCache(
                        id,
                        async () => {
                            return await this.fetchSecured(wiki.url('?api/hpf/helloasso/payment/getToken'),{method:'POST'})
                            .then(async (token)=>{
                                let formData = new FormData()
                                formData.append('anti-csrf-token',token)
                                return await this.fetchSecured(
                                    wiki.url(`?api/hpf/helloasso/payment/find/${formattedDate}/${String(Math.round(Number(amount)*100))}`),
                                    {
                                        method:'POST',
                                        body: new URLSearchParams(formData),
                                        headers: (new Headers()).append('Content-Type','application/x-www-form-urlencoded')
                                    }
                                )
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
        resolve(name,results = null){
            if (Array.isArray(this.cacheResolveReject?.[name])){
                const listOfResolveReject = this.cacheResolveReject[name]
                this.cacheResolveReject[name] = []
                listOfResolveReject.forEach(({resolve})=>resolve(results === null ? this?.[name] : results))
            }
        },
        async searchEntry(){
            if (this.refreshing){
                return // do nothing
            }
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
                                this.cacheEntries[entry.id_fiche] = entry
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
                if (this.params?.formsids && Object.keys(this.params?.formsids).length === 1){
                    this.selectedForm = Object.keys(this.params.formsids)[0]
                }
            } catch (error) {
                console.error(error)
            }
        }
    },
    watch:{
        notSearching(){
            this.resolve('notSearching')
        },
        newPayment:{
            deep: true,
            handler(value){
                if (value.origin === 'helloasso' && value.date?.length > 0 && Number(value.total) > 0){
                    // refresh
                    this.refreshHelloAssoIds()
                        .catch(this.manageError)
                }
            }
        },
        search:{
            deep: true,
            handler(){
                this.searchEntry().catch(this.manageError)
            }
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