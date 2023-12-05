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

window.hpfImportTableWrapper = {
    updateValue(event,name,key){
        event.stopPropagation()
        event.preventDefault()
        const elem = event.target
        const mainVue = retrievMainVue(elem)
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
            columns: [],
            message: null,
            messageClass: {['alert-info']:true},
            params: null,
            rows: {},
            toggleRefresh: false,
            token: '',
            uuid: null,
            values: null
        }
    },
    computed:{
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
        addRows(){
            this.values.forEach((value,idx)=>{
                const formattedData = {}
                this.columns.forEach((col)=>{
                    formattedData[col.data] = value?.[col.data] ?? (col.data === 'date' ? today : '')
                })
                this.$set(this.rows,idx,formattedData)
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
                            return `<input type="text" size="${maxSize}" value="${dataVal}" onChange="hpfImportTableWrapper.updateValue(event,${JSON.stringify(name).replace(/"/g,"'")},${row.id})"/>`;
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
                                    ${row?.isGroup === 'x' ? '': 'disabled style="display:none;"'}
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
                    render: (data,type)=>{
                        if (type === 'display'){
                            const cents = Math.round((data % 1)*100)
                            const euros = Math.round(data-cents/100)
                            return `${euros},${cents < 10 ? '0' : ''}${cents} €`
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
                                <select value="${dataVal}" onChange="hpfImportTableWrapper.updateValue(event,${JSON.stringify(name).replace(/"/g,"'")},${row.id})">
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
        getColumns(){
            if (this.columns.length == 0){
                const data = {columns:[]}
                const defaultcolumnwidth = '100px';
                const width = defaultcolumnwidth.length > 0 ? {width:defaultcolumnwidth}: {}
                // case à cocher : importer
                // fiche associée / créer une fiche
                // acteur/groupe ... type
                this.appendColumn('firstname',data,width)
                this.appendColumn('name',data,width)
                this.appendColumn('address',data,width)
                this.appendColumn('addressComp',data,width)
                this.appendColumn('postalcode',data,width,true,5)
                this.appendColumn('town',data,width)
                this.appendColumn('email',data,width)
                this.appendColumn('number',data,width)
                this.appendColumnEuros('value',data,width)
                this.appendColumn('date',data,width,true,10)
                this.appendColumnSelect('isGroup',data,width,{'':'Adhésion individuelle','x':'Adhésion groupe'})
                this.appendColumnGroupName(data,width)
                this.appendColumn('comment',data,width,false)
                // prénom (retour ligne prénom fiche associée)
                // nom (retour ligne nom fiche associée)
                // email (retour email nom fiche associée)
                // Valeur adhésion
                // Type adhésion (à choisir standard/libre)
                // Date adhésion (celle du jour)
                // Si fiche associée : est adhérent de la même année ? valeur adhésion déjà payée ?
                // adresse (retour ligne adresse fiche associée)
                // Code postal (retour ligne code postal fiche associée)
                // Ville (retour ligne Ville fiche associée)
                // Groupe BDD associé
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
        updateRows(){
            this.getColumns()
            this.removeRows()
            this.addRows()
            this.toggleRefresh = !this.toggleRefresh
        },
        updateValue(key,name,newValue){
            const sanitizedKey = Number(key)
            if (sanitizedKey >= 0 && sanitizedKey < this.values.length){
                this.$set(this.values[sanitizedKey],name,newValue)
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
    },
    watch:{
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
            <template #sumtranslate>{{ sumtranslate }}</template>
        </dyn-table>
    </div>
    `
}