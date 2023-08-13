/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import DynTable from '../../../bazar/presentation/javascripts/components/DynTable.js'
import TemplateRenderer from '../../../bazar/presentation/javascripts/components/TemplateRenderer.js'

const isVueJS3 = (typeof window.Vue.createApp == "function");

const defaultValues = {}
for (let index = 1; index <= 12; index++) {
    defaultValues[`${index}`] = 0
}
defaultValues.other = 0

const defaultData = {}
for (let index = 1; index <= 4; index++) {
    defaultData[`${index}`] = {
        id: `${index}`,
        values: {...defaultValues}
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
            params: null,
            payments: {...defaultData},
            rows: {},
            uuid: null,
            year: (new Date()).getFullYear()
        }
    },
    computed:{
        element(){
            return isVueJS3 ? this.$el.parentNode : this.$el
        },
        sumtranslate(){
            return TemplateRenderer.render('HpfPayementsTable',this,'sumtranslate')
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
                                formattedData[col.data] = TemplateRenderer.render('HpfPayementsTable',this,'name',{},[['{id}',(row?.id ?? id ) ?? 'unknown']])
                                break
                            case 'year':
                                formattedData[col.data] = this.getSum(row)
                                break
                            default:
                                formattedData[col.data] = row.values?.[col.data] ?? row?.[col.data] ?? ''
                                break
                        }
                    }
                })
                this.$set(this.rows,id,formattedData)
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
                        title: TemplateRenderer.render('HpfPayementsTable',this,'firstcolumntitle'),
                        footer: ''
                    },
                    ...width
                })
                data.columns.push({
                    ...{
                        data: 'year',
                        class: 'sum-activated',
                        title: TemplateRenderer.render('HpfPayementsTable',this,'yeartotal'),
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
                            title: TemplateRenderer.render('HpfPayementsTable',this,associations?.[key] ?? key),
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
            return Object.entries(row.values)
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
            let shouldReset = true
            const unwatch = this.$watch('isLoading',()=>{
                unwatch()
                shouldReset = false
            })
            setTimeout(()=>{
                if (shouldReset){
                    this.$emit('update-loading',false)
                }
            },5000)
        },
        updatePayments(){
            this.getColumns()
            this.removeRows()
            this.addRows()
        },
    },
    mounted(){
        $(this.element).on('dblclick',function(e) {
            return false;
        });
        const rawParams = this.element.dataset?.params
        if (rawParams){
            try {
                this.params = JSON.parse(rawParams)
            } catch (error) {
                console.error(error)
            }
        }
        this.secureResetIsloading().catch(this.manageError)
        this.updatePayments() // to force display table
        // example waiting loading
        this.payments[3].values[6] = 60
        this.payments[1].values[12] = 20
        this.$emit('update-loading',false)
    },
    watch:{
        payments:{
            deep: true,
            handler(){
                this.updatePayments()
            }
        },
    },
    template: `
    <div>
        <dyn-table :columns="columns" :rows="rows">
            <template #dom>&lt;'row'&lt;'col-sm-12'tr>>&lt;'row'&lt;'col-sm-6'i>&lt;'col-sm-6'&lt;'pull-right'B>>></template>
            <template #sumtranslate>{{ sumtranslate }}</template>
        </dyn-table>
    </div>
    `
}