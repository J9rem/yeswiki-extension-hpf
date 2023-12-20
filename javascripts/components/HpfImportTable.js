/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-import-payments
 */

import DynTable from 'DynTable'
import TemplateRenderer from 'TemplateRenderer'

const isVueJS3 = (typeof window.Vue.createApp == "function");

const retrievMainVue = (element) => {
    const mainParent = $(element).closest('.dynamic-hpf-import-memberships-action')
    if (mainParent?.length > 0){
        const currentParent = $(mainParent).parent().find('.dynamic-hpf-import-memberships-action > [data-values]')
        if (currentParent?.length > 0 && (currentParent?.[0]?.__vue__) !== null){
            return currentParent[0].__vue__
        }
        throw new Error('HpfImportTable vue not found')
    }
    throw new Error('Main Import Vue action vue not found')
}

const timeToStr = (timeObj) => {
    const day = timeObj.getDate()
    const month = timeObj.getMonth() + 1
    return `${day < 10 ? '0' : ''}${day}/${month < 10  ? '0': '' }${month}/${timeObj.getFullYear()}`
}

const today = timeToStr(new Date())
const currentYear = String((new Date()).getFullYear())

const getYear = (strTime) => {
    try {
        const matchResult = strTime.match(/^[0-9]{2}\/[0-9]{2}\/([0-9]{2,4})$/)
        if (matchResult?.[1]?.length > 0){
            return matchResult[1]
        }
        const date = new Date(strTime)
        return String(date.getFullYear())
    } catch (error) {
        return currentYear
    }
}

const getVue = (event) => {
    event.stopPropagation()
    event.preventDefault()
    const elem = event.target
    const mainVue = retrievMainVue(elem)
    return {elem,mainVue}
}

const calculateCents = (value) => {
    return Math.round((Number(value) % 1) *100)/100
}
const calculateEuros = (value) => {
    return Math.round(Number(value) - calculateCents(value))
}

window.hpfImportTableWrapper = {
    toggleCheckbox(event,name,key){
        const {mainVue} = getVue(event)
        mainVue.toggleCheckbox(key,name)
    },
    updateCents(event,key){
        const {elem,mainVue} = getVue(event)
        const value = $(elem).val()
        mainVue.updateValue(key,'value',calculateEuros(mainVue.values?.[key]?.value ?? 0) + calculateCents(value/100))
    },
    updateEuros(event,key){
        const {elem,mainVue} = getVue(event)
        const value = $(elem).val()
        mainVue.updateValue(key,'value',calculateEuros(value) + calculateCents(mainVue.values?.[key]?.value ?? 0))
    },
    updateValue(event,name,key){
        const {elem,mainVue} = getVue(event)
        const value = $(elem).val()
        mainVue.updateValue(key,name,value)
    }
}

export default {
    model: {
        prop: 'isLoading',
        event: 'update-loading'
    },
    components: {DynTable},
    props: ['isLoading'],
    data: function() {
        return {
            asyncHelper: null,
            cache:{
                college1:{
                    email:{}
                },
                college2:{
                    email:{}
                }
            },
            canProceed: false,
            columns: [],
            message: null,
            messageClass: {['alert-info']:true},
            params: null,
            processing: false,
            rows: {},
            toggleRefresh: false,
            token: '',
            uuid: null,
            values: null
        }
    },
    computed:{
        buttonTxt(){
            return TemplateRenderer.render('HpfImportTable',this,'tbtntxt')
        },
        element(){
            return isVueJS3 ? this.$el.parentNode : this.$el
        },
        loading:{
            get(){
                return this.isLoading
            },
            set(value){
                return this.$emit('update-loading',value === true)
            }
        },
    },
    methods:{
        async addEntryOrAppend(data,append = false,catchBadToken = true){
            const type = data.isGroup === 'x' ? 'college2' : 'college1'
            const formId = this.params[type]
            const mode  = append === true ? 'appendPayment' : 'createEntry'
            return await this.asyncHelper.fetch(
                window.wiki.url(`?api/hpf/importmembership/${mode}/${type}/${formId}`),
                'post',
                {
                    data,
                    'anti-csrf-token': this.token
                }
            ).catch(async (error)=>{
                if (catchBadToken && error?.errorMsg === 'badToken'){
                    // get newToken
                    const tokenRaw = await this.asyncHelper.fetch(
                        window.wiki.url(`?api/hpf/importmembership/gettoken`),
                        'post',
                        {}
                    )
                    if (!(tokenRaw?.['anti-csrf-token']?.length > 0)){
                        console.error('new token not found')
                    } else {
                        this.token = tokenRaw['anti-csrf-token']
                        // retry once 
                        return await this.addEntryOrAppend(data,append,false)
                    }
                }
                return Promise.reject(error)
            })
        },
        addRows(){
            this.values.forEach((value,idx)=>{
                const formattedData = {}
                this.columns.forEach((col)=>{
                    formattedData[col.data] = value?.[col.data] ?? ''
                })
                this.$set(this.rows,idx,formattedData)
            })
        },
        appendCheckBox(data,width,dataName,translation,renderFunc){
            data.columns.push({
                ...{
                    data: dataName,
                    title: TemplateRenderer.render('HpfImportTable',this,translation),
                    footer: '',
                    render: (data,type,row)=>{
                        if (type === 'display'){
                            return renderFunc(data,type,row)
                        } else if (type == 'sort'){
                            return row?.id ?? data
                        }
                        return data
                    },
                    orderable: false
                },
                ...width
            })
        },
        appendCheckBoxforEntryCreation(data,width){
            this.appendCheckBox(data,width,'processEntry','taddentryorpayment',(data,type,row)=>{
                let error = ''
                let appendPart = ''
                const defaultError = TemplateRenderer.render('HpfImportTable',this,'tcreateentrynotpossible')
                const associatedId = this.getAssociatedId(row.id)
                if (!this.checkEmail(row)){
                    error = defaultError + TemplateRenderer.render('HpfImportTable',this,'temailbadlyformatted')
                } else if (row?.postalcode?.length > 0 && !this.checkPostalCode(row)){
                    error = defaultError + TemplateRenderer.render('HpfImportTable',this,'tpostalcodebadlyformatted')
                } else if (!(row?.postalcode?.length > 0) && !(row?.dept?.length > 0)){
                    error = defaultError + TemplateRenderer.render('HpfImportTable',this,'tpostalcodeordeptmissing')
                } else if (associatedId?.length > 0){
                    this.values[row.id].associatedEntryId = associatedId
                    this.values[row.id].canAdd = false
                    if (this.values[row.id]?.registeredPayments?.includes(row?.number ?? '')){
                        appendPart = `
                            <a
                                href="${window.wiki.url(`?${associatedId}/iframe`)}"
                                style="color:green;text-decoration: none;"
                                class="modalbox"
                                data-toggle="tooltip"
                                data-placement="right"
                                data-iframe="1"
                                data-size="modal-lg"
                                title="${TemplateRenderer.render('HpfImportTable',this,'talreadyappended')}"
                                onMouseover="if(!this.hasAttribute('data-original-title')){$(this).tooltip('show');};this.removeAttribute('onMouseover')"
                                >✅</a>
                        `
                        this.values[row.id].canAppend = false
                    } else {
                        this.values[row.id].canAppend = true
                        const warning = TemplateRenderer.render('HpfImportTable',this,'tappendinsteadofcreate')
                        appendPart = `
                            <a
                                href="${window.wiki.url(`?${associatedId}/iframe`)}"
                                style="color:orange;text-decoration: none;"
                                class="modalbox"
                                data-toggle="tooltip"
                                data-placement="right"
                                data-iframe="1"
                                data-size="modal-lg"
                                title="${warning}"
                                onMouseover="if(!this.hasAttribute('data-original-title')){$(this).tooltip('show');};this.removeAttribute('onMouseover')"
                                >❗</a>
                        `
                    }
                } else if (this.values.filter((v,idx)=>idx < row.id && v.email == row.email).length > 0){
                    error = defaultError + TemplateRenderer.render('HpfImportTable',this,'temailalreadyused')
                } else if (row?.isGroup === 'x' && !(row?.groupName?.length > 0)){
                    error = defaultError + TemplateRenderer.render('HpfImportTable',this,'tgroupnameempty')
                } else if (row?.isGroup !== 'x' && !(row?.firstname?.length > 0)){
                    error = defaultError + TemplateRenderer.render('HpfImportTable',this,'tfirstnameempty')
                } else if (row?.isGroup !== 'x' && !(row?.name?.length > 0)){
                    error = defaultError + TemplateRenderer.render('HpfImportTable',this,'tnameempty')
                }
                const input = `
                    <span ${(this.processing === true || error.length > 0) ? '' : `onClick="hpfImportTableWrapper.toggleCheckbox(event,'processEntry',${row.id})"`}>
                        <input 
                            type="checkbox"
                            ${(data === true && error.length == 0) ? ' checked' : ''}
                            ${(this.processing === true || error.length > 0 || (this.values[row.id].canAppend === false && associatedId?.length > 0)) ? ' disabled' : ''}
                        />
                        <span></span>
                    </span>
                    `
                if (error.length > 0){
                    this.values[row.id].canAdd = false
                    this.values[row.id].canAppend = false
                    this.values[row.id].processEntry = false
                    return  `
                            <div>
                                ${input}${appendPart} <span
                                    style="color:red;"
                                    data-toggle="tooltip"
                                    data-placement="right"
                                    title="${error}"
                                    onMouseover="if(!this.hasAttribute('data-original-title')){$(this).tooltip('show');};this.removeAttribute('onMouseover')"
                                    >⚠</span>
                            </div>
                            `
                } else {
                    if (this.values[row.id].canAppend === false && !(associatedId?.length > 0)){
                        this.values[row.id].canAdd = true
                    }
                    return input+appendPart
                }
            })
        },
        appendCheckBoxforEntryVisibility(data,width){
            this.appendCheckBox(data,width,'canDisplayPublic','tvisibility',(data,type,row)=>{
                return `
                <span ${this.processing === true ? '' : `onClick="hpfImportTableWrapper.toggleCheckbox(event,'canDisplayPublic',${row.id})"`}>
                    <input 
                        type="checkbox"
                        ${data === true ? ' checked' : ''}
                        ${this.processing === true  ? ' disabled' : ''}
                    />
                    <span></span>
                </span>
                `
            })
        },
        appendColumn(name,data,width,canEdit=true,maxSize=15){
            data.columns.push({
                ...{
                    data: name,
                    title: TemplateRenderer.render('HpfImportTable',this,`t${name.toLowerCase()}`),
                    footer: ''
                },
                ...(canEdit ? {
                    render: (data,type,row)=>{
                        if (type === 'display'){
                            const dataVal = typeof data === 'string' ? data : ''
                            return `
                                <input
                                    type="text"
                                    size="${maxSize}"
                                    value="${dataVal}"
                                    onChange="hpfImportTableWrapper.updateValue(event,${JSON.stringify(name).replace(/"/g,"'")},${row.id})"
                                    ${this.processing === true ? ' disabled' : ''}
                                />
                            `;
                        }
                        return data
                    }
                } : {}),
                ...width
            })
        },
        appendColumnGroupName(data,width){
            data.columns.push({
                ...{
                    data: 'groupName',
                    title: TemplateRenderer.render('HpfImportTable',this,'tgroupname'),
                    footer: '',
                    render: (data,type,row)=>{
                        if (type === 'display'){
                            const dataVal = typeof data === 'string' ? data : ''
                            return `
                                <input
                                    type="text"
                                    size="15"
                                    value="${dataVal}"
                                    onChange="hpfImportTableWrapper.updateValue(event,'groupName',${row.id})"
                                    ${row?.isGroup === 'x' ? '': 'style="display:none;"'}
                                    ${(row?.isGroup !== 'x' || this.processing === true) ? 'disabled' : ''}
                                />
                            `;
                        }
                        return data
                    }
                },
                ...width
            })
        },
        appendColumnEuros(name,data,width){
            data.columns.push({
                ...{
                    data: name,
                    title: TemplateRenderer.render('HpfImportTable',this,`t${name.toLowerCase()}`),
                    footer: '',
                    render: (data,type,row)=>{
                        if (type === 'display'){
                            const cents = calculateCents(data)*100
                            const formattedCents = (cents < 10 ? '0' : '')+cents
                            return `
                                <span style="white-space: nowrap;">
                                <input
                                type="text"
                                value="${calculateEuros(data)}"
                                size="3"
                                onChange="hpfImportTableWrapper.updateEuros(event,${row.id})"
                                ${this.processing === true ? ' disabled' : ''}
                                />,<input
                                type="text"
                                value="${formattedCents}"
                                size="2"
                                onChange="hpfImportTableWrapper.updateCents(event,${row.id})"
                                ${this.processing === true ? ' disabled' : ''}
                            />&nbsp;€</span>`
                        }
                        return data
                    }
                },
                ...width
            })
        },
        appendColumnSelect(name,data,width,options){
            data.columns.push({
                ...{
                    data: name,
                    title: TemplateRenderer.render('HpfImportTable',this,`t${name.toLowerCase()}`),
                    footer: '',
                    render: (data,type,row)=>{
                        if (type === 'display'){
                            const dataVal = typeof data === 'string' ? data.toLowerCase() : ''
                            return `
                                <select
                                    value="${dataVal}"
                                    onChange="hpfImportTableWrapper.updateValue(event,${JSON.stringify(name).replace(/"/g,"'")},${row.id})"
                                    ${this.processing === true ? ' disabled' : ''}
                                    >
                                    ${Object.entries(options)
                                        .map(([value,txt])=>{
                                            const valLower = typeof value === 'string' ? value.toLowerCase() : ''
                                            return `<option value="${valLower}"${ valLower === dataVal ? ' selected' : ''}>${txt}</option>`
                                        })
                                        .join("\n")}
                                </select>
                            `;
                        }
                        return data
                    }
                },
                ...width
            })
        },
        appendMessage(message){
            this.message = ((this.message.length === 0)
                ? ''
                : `${this.message}<br>`)+message
        },
        checkEmail(data){
            return data?.email?.length > 0
                && String(data.email)
                    .toLowerCase()
                    .match(
                        /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
                    )
        },
        checkPostalCode(data){
            return data?.postalcode?.length > 0
                && String(data.postalcode)
                    .toUpperCase()
                    .match(
                        /^(?:[0-9]{5}|2[AB][0-9]{3})$/
                    )
        },
        getAssociatedId(key){
            if (this.values?.[key]?.associatedEntryId?.length > 0){
                return this.values[key].associatedEntryId
            }
            if (this.values?.[key]?.email?.length > 0){
                return this.cache[this.getCollegeForCache(key)].email?.[this.values[key].email] ?? ''
            }
            return ''
        },
        getCollegeForCache(key){
            return this.values?.[key]?.isGroup === 'x' ? 'college2' : 'college1'
        },
        getColumns(){
            if (this.columns.length == 0){
                const data = {columns:[]}
                const defaultcolumnwidth = '100px';
                const width = defaultcolumnwidth.length > 0 ? {width:defaultcolumnwidth}: {}
                this.appendCheckBoxforEntryCreation(data,width)
                this.appendColumn('firstname',data,width)
                this.appendColumn('name',data,width)
                this.appendCheckBoxforEntryVisibility(data,width)
                this.appendColumn('postalcode',data,width,true,5)
                this.appendColumn('town',data,width)
                this.appendColumn('dept',data,width,true,3)
                this.appendColumn('wantedStructure',data,width)
                this.appendColumn('email',data,width)
                this.appendColumn('number',data,width)
                this.appendColumnEuros('value',data,width)
                this.appendColumn('date',data,width,true,10)
                this.appendColumnSelect('paymentType',data,width,{
                    'structure': TemplateRenderer.render('HpfImportTable',this,`tstructurepaymenttype`),
                    'cheque': TemplateRenderer.render('HpfImportTable',this,`tchequepaymenttype`),
                    'virement': TemplateRenderer.render('HpfImportTable',this,`tvirementpaymenttype`),
                    'especes': TemplateRenderer.render('HpfImportTable',this,`tespecespaymenttype`)
                })
                this.appendColumnSelect('year',data,width,Object.fromEntries(
                    [-2,-1,0,1].map((offset)=>{
                        const year = String(Number(currentYear) + offset)
                        return [year,year]
                    })
                ))
                this.appendColumnSelect('isGroup',data,width,{
                    '': TemplateRenderer.render('HpfImportTable',this,`tpersonalmembership`),
                    'x': TemplateRenderer.render('HpfImportTable',this,`tgroupmembership`)
                })
                this.appendColumnSelect('membershipType',data,width,{
                    'standard': TemplateRenderer.render('HpfImportTable',this,`tstandardmembership`),
                    'soutien': TemplateRenderer.render('HpfImportTable',this,`tsupportmembership`),
                    'ajuste': TemplateRenderer.render('HpfImportTable',this,`tadjustedmembership`),
                    'libre': TemplateRenderer.render('HpfImportTable',this,`tfreemembership`)
                })
                this.appendColumnGroupName(data,width)
                this.appendColumn('comment',data,width,false)
                this.columns = data.columns
            }
            return this.columns
        },
        getUuid(){
            if (this.uuid === null){
                this.uuid = crypto.randomUUID()
            }
            return this.uuid
        },
        manageError(error = null){
            if (error && wiki.isDebugEnabled){
                console.error(error)
            }
            return null
        },
        async process(){
            if (this.asyncHelper
                && this.processing === false
                ){
                try {
                    this.processing = true
                    this.messageClass = {['alert-info']:true}
                    this.message = TemplateRenderer.render('HpfImportTable',this,`tprocessing`)
                    for (let key = 0; key < this.values.length; key++) {
                        const v = this.values[key];
                        if (v.processEntry){
                            if (v.canAdd){
                                await this.addEntryOrAppend(v,false)
                                    .then((entry)=>{
                                        if (entry?.id_fiche?.length > 0){
                                            this.appendMessage(`✅ ajout OK pour <a href="${window.wiki.url(`?${entry.id_fiche}`)}" class="newtab">${entry.id_fiche}</a>`)
                                            this.values[key].canAdd = false
                                            this.values[key].processEntry = false
                                            this.values[key].associatedEntryId = entry.id_fiche
                                            this.values[key].registeredPayments.push(v.number)
                                            this.cache[this.getCollegeForCache(key)].email[v.email] = entry.id_fiche
                                            return entry.id_fiche
                                        } else {
                                            throw new Error('empty entryId')
                                        }
                                    })
                                    .catch((error)=>{
                                        this.asyncHelper.manageError(error)
                                        this.appendMessage(`❌ la fiche avec l'e-mail '${v.email}' n'a pas été ajoutée !`)
                                    })
                            } else if (v.canAppend){
                                const entryId = this.getAssociatedId(key)
                                await this.addEntryOrAppend(v,true)
                                    .then((entry)=>{
                                        if (entry?.id_fiche?.length > 0){
                                            this.appendMessage(`✅ ajout du paiment fait pour <a href="${window.wiki.url(`?${entryId}`)}" class="newtab">${entryId}</a>`)
                                            this.values[key].canAppend = false
                                            this.values[key].processEntry = false
                                            this.values[key].registeredPayments.push(v.number)
                                            return true
                                        } else {
                                            throw new Error('payment not added')
                                        }
                                    })
                                    .catch((error)=>{
                                        if (error?.errorMsg === 'existing payment'){
                                            this.values[key].canAppend = false
                                            this.values[key].processEntry = false
                                            this.values[key].registeredPayments.push(v.number)
                                            this.appendMessage(`🚧 le paiment n'a pas été ajouté pour la fiche <a href="${window.wiki.url(`?${entryId}`)}" class="newtab">${entryId}</a> car il existe déjà !`)
                                        } else {
                                            this.asyncHelper.manageError(error)
                                            this.appendMessage(`❌ le paiment n'a pas été ajouté pour la fiche <a href="${window.wiki.url(`?${entryId}`)}" class="newtab">${entryId}</a> !`)
                                        }
                                    })
                            }
                        }
                    }
                    this.appendMessage('✅ Fin')
                    this.processing = false
                } catch (error) {
                    this.appendMessage('❌ Error : '+error)
                    this.processing = false
                }
            }
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
        refreshCanProceed(){
            this.canProceed = this.values?.some((v)=>(v.processEntry && (v.canAdd || v.canAppend))) ?? false
        },
        setDefaultValues(){
            this.values.forEach((v,k)=>{
                if (!(v?.date?.length > 0)){
                    this.values[k].date = today
                }
                if (!(v?.year?.length > 0)){
                    this.values[k].year = getYear(this.values[k].date)
                }
                const membershipValue = Math.round(v?.value ?? 0)
                switch (membershipValue) {
                    case 15:
                        this.values[k].membershipType = (v?.isGroup === 'x')
                            ? 'ajuste'
                            : 'standard'
                        break;
                    case 30:
                        this.values[k].membershipType = (v?.isGroup === 'x')
                            ? 'libre'
                            : 'soutien'
                        break;
                    case 100:
                        this.values[k].membershipType = 'standard'
                        this.values[k].isGroup = 'x'
                        break;
                    case 200:
                        this.values[k].membershipType = 'soutien'
                        this.values[k].isGroup = 'x'
                        break;
                
                    default:
                        if (membershipValue % 15 === 0){
                            this.values[k].membershipType = 'ajuste'
                            this.values[k].isGroup = 'x'
                        } else {
                            this.values[k].membershipType = 'libre'
                        }
                        break;
                }
                if (v?.associatedEntryId?.length > 0 && v?.email?.length > 0){
                    this.cache[this.getCollegeForCache(k)].email[v.email] = v.associatedEntryId
                }
                this.values[k].processEntry = true
                this.values[k].canAdd = true
                this.values[k].canAppend = true
                this.values[k].canDisplayPublic = (this.values[k].visibility === 'x')
                this.values[k].registeredPayments = []
                this.values[k].paymentType = (this.values[k].receivedbyhpf === 'x')
                    ? 'cheque'
                    : 'structure'
            })
        },
        toggleCheckbox(key,name){
            const sanitizedKey = Number(key)
            if (sanitizedKey >= 0 && sanitizedKey < this.values.length){
                const previous = this.values[sanitizedKey]?.[name] ?? false
                this.$set(this.values[sanitizedKey],name,(previous !== true))
                this.updateRows()
            }
        },
        async updateEmailStatusIfNeeded(key){
            const email = this.values?.[key]?.email ?? ''
            if (this.asyncHelper
                && email?.length > 0
                && this.processing === false
                && !(this.cache[this.getCollegeForCache(key)].email?.[email]?.length >0)
                ){
                this.processing = true
                const cacheKey = this.getCollegeForCache(key)
                this.cache[cacheKey].email[email] = ''
                try {
                    this.asyncHelper.fetch(window.wiki.url(`?api/forms/${this.params[cacheKey]}/entries`,{query:`bf_mail=${email}`}))
                        .then((data)=>{
                            const entries = Object.values(data)
                            if (entries.length > 0){
                                this.cache[cacheKey].email[email] = entries[0].id_fiche ?? ''
                            }
                        })
                        .catch(this.asyncHelper.manageError)
                        .finally(()=>{
                            this.processing = false
                        })
                } catch (error) {
                    this.processing = false
                }
            }
        },
        updateRows(){
            this.getColumns()
            this.removeRows()
            this.addRows()
            this.refreshCanProceed()
            this.toggleRefresh = !this.toggleRefresh
            setTimeout(() => {
                this.toggleRefresh = !this.toggleRefresh
            }, 300)
        },
        updateValue(key,name,newValue){
            const sanitizedKey = Number(key)
            if (sanitizedKey >= 0 && sanitizedKey < this.values.length){
                this.$set(this.values[sanitizedKey],name,newValue)
                if (name === 'email'){
                    this.updateEmailStatusIfNeeded(key)
                }
            }
        }
    },
    mounted(){
        $(this.element).on('dblclick',function(e) {
            return false;
        });
        const rawValues = this.element.dataset?.values
        if (rawValues){
            try {
                this.values = JSON.parse(rawValues)
                this.setDefaultValues()
            } catch (error) {
                console.error(error)
            }
        }
        const rawParams = this.element.dataset?.params
        if (rawParams){
            try {
                this.params = JSON.parse(rawParams)
                this.token = this.params?.['anti-csrf-token'] ?? ''
            } catch (error) {
                console.error(error)
            }
        }
        this.updateRows() // to force display table
        this.loading = false
        import(wiki.baseUrl.replace(/\/?\??$/,'')+'/tools/alternativeupdatej9rem/javascripts/asyncHelper.js')
            .then((module)=>{this.asyncHelper = module.default})
            .catch(()=>{
                this.messageClass = {['alert-danger']:true}
                this.message = 'Ce module ne pourra pas fonctionner correctement tant que l\'extension "alternativeupdatej9rem" n\'est pas installée !'
            })
    },
    watch:{
        processing(){
            this.updateRows()
        },
        values:{
            deep: true,
            handler(n){
                this.updateRows()
            }
        }
    },
    template: `
    <div>
        <div v-if="message" :class="{...{alert:true},...(message ? messageClass : {['alert-info']:true})}">
            <span v-html="message"></span>
        </div>
        <dyn-table :columns="columns" :rows="rows" :forceRefresh="toggleRefresh" :uuid="getUuid()">
            <template #dom>&lt;'row'&lt;'col-sm-12'tr>>&lt;'row'&lt;'col-sm-6'i>&lt;'col-sm-6'&lt;'pull-right'B>>></template>
        </dyn-table>
        <button class="btn btn-danger" :disabled="processing || !canProceed" @click.prevent.stop="process">{{ buttonTxt }}</button>
    </div>
    `
}