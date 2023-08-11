/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    props: ['isLoading'],
    data: function() {
        return {
            columns: [],
            dataTable: null,
            params: null,
            payments: {...defaultData},
            templatesForRendering: {},
            uuid: null,
            year: (new Date()).getFullYear()
        };
    },
    computed:{
        element(){
            return isVueJS3 ? this.$el.parentNode : this.$el
        }
    },
    methods: {
        addRows(dataTable,columns){
            const formattedDataList = []
            Object.entries(this.payments).forEach(([id,row])=>{
                const formattedData = {}
                columns.forEach((col)=>{
                    if (!(typeof col.data === 'string')){
                        formattedData[col.data] = ''
                    } else {
                        switch (col.data) {
                            case 'name':
                                formattedData[col.data] = this.render('name',{['{id}']:(row?.id ?? id ) ?? 'unknown'})
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
                formattedData.id = row?.id ?? id
                formattedDataList.push(formattedData)
            })
            dataTable.rows.add(formattedDataList)
        },
        getColumns(){
            if (this.columns.length == 0){
                const data = {columns:[]}
                const defaultcolumnwidth = '100px';
                const width = defaultcolumnwidth.length > 0 ? {width:defaultcolumnwidth}: {}
                data.columns.push({
                    ...{
                        data: 'name',
                        title: this.render('firstcolumntitle'),
                        footer: ''
                    },
                    ...width
                })
                data.columns.push({
                    ...{
                        data: 'year',
                        class: 'sum-activated',
                        title: this.render('yeartotal'),
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
                            title: this.render(associations?.[key] ?? key),
                            footer: ''
                        },
                        // ...width
                    })
                })
                this.columns = data.columns
            }
            return this.columns
        },
        getDatatableOptions(){
            const buttons = []
            DATATABLE_OPTIONS.buttons.forEach((option) => {
              buttons.push({
                ...option,
                ...{ footer: true },
                ...{
                  exportOptions: (
                    option.extend != 'print'
                      ? {
                        orthogonal: 'sort', // use sort data for export
                        columns(idx, data, node) {
                          return !$(node).hasClass('not-export-this-col')
                        }
                      }
                      : {
                        columns(idx, data, node) {
                          const isVisible = $(node).data('visible')
                          return !$(node).hasClass('not-export-this-col') && (
                            isVisible == undefined || isVisible != false
                          ) && !$(node).hasClass('not-printable')
                        }
                      })
                }
              })
            })
            return {
                ...DATATABLE_OPTIONS,
                ...{
                  searching: true,// allow search but ue dom option not display filter
                  dom:'lrtip', // instead of default lfrtip , with f for filter, see help : https://datatables.net/reference/option/dom
                  footerCallback: ()=>{
                    this.updateFooter()
                  },
                  buttons
                }
              }
        },
        getDatatable(){
            if (this.dataTable === null){
                // create dataTable
                const columns = this.getColumns()
                const options = this.getDatatableOptions()
                options.columns = columns
                options['scrollX'] = true
                this.dataTable = $(this.$refs.dataTable).DataTable(options)
                $(this.dataTable.table().node()).prop('id',this.getUuid())
                this.initFooter(columns)
            }
            return this.dataTable
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
        getTemplateFromSlot(name,params){
            const key = name+'-'+JSON.stringify(params)
            if (!(key in this.templatesForRendering)){
                if (name in this.$scopedSlots){
                    const slot = this.$scopedSlots[name]
                    const constructor = window.Vue.extend({
                        render: function(h){
                            return h('div',{},slot(params))
                        }
                    })
                    const instance = new constructor()
                    instance.$mount()
                    let outerHtml = '';
                    for (let index = 0; index < instance.$el.childNodes.length; index++) {
                        outerHtml += instance.$el.childNodes[index].outerHTML || instance.$el.childNodes[index].textContent
                    }
                    this.templatesForRendering[key] = outerHtml
                } else {
                    this.templatesForRendering[key] = name
                }
            }
            return this.templatesForRendering[key]
        },
        getUuid(){
            if (this.uuid === null){
                this.uuid = crypto.randomUUID()
            }
            return this.uuid
        },
        initFooter(columns){
            const footerNode = this.dataTable.footer().to$()
            if (footerNode[0] !== null){
                const footer = $('<tr>')
                let displayTotal = true
                columns.forEach((col)=>{
                    if ('footer' in col && col.footer.length > 0){
                        const element = $(col.footer)
                        const isTh = $(element).prop('tagName') === 'TH'
                        footer.append(isTh ? element : $('<th>').append(element))
                    } else if (displayTotal) {
                        displayTotal = false
                        footer.append($('<th>').text(this.render('sum')))
                    } else {
                        footer.append($('<th>'))
                    }
                })
                footerNode.html(footer)
            }
        },
        manageError(error = null){
            if (error && wiki.isDebugEnabled){
                console.error(error)
            }
            return null
        },
        removeRows(dataTable){
            dataTable.rows((idx,data,node)=>{
                return data?.id > 0 && data?.id < 5
            }).remove()
        },
        render(name,replacement = {}){
            let output = this.getTemplateFromSlot(name,{})
            Object.entries(replacement).forEach(([anchor,replacement]) => {
                output = output.replace(anchor,replacement)
            });
            return output
        },
        sanitizeValue(val) {
            const sanitizedValue = (typeof val === 'object') ? (val?.display || '') : val
            return (isNaN(sanitizedValue)) ? 1 : Number(sanitizedValue)
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
            const dataTable = this.getDatatable()
            const columns = this.getColumns()
            this.removeRows(dataTable)
            this.addRows(dataTable,columns)
            dataTable.draw()
        },
        updateFooter(){
            if (this.dataTable !== null){
                const activatedRows = []
                this.dataTable.rows({ search: 'applied' }).every(function() {
                  activatedRows.push(this.index())
                })
                this.dataTable.columns('.sum-activated').every((indexCol) => {
                    let col = this.dataTable.column(indexCol)
                    let sum = 0
                    activatedRows.forEach((indexRow) => {
                      const value = this.dataTable.row(indexRow).data()[col.dataSrc()]
                      sum += this.sanitizeValue(Number(value))
                    })
                    this.dataTable.footer().to$().find(`> tr > th:nth-child(${indexCol+1})`).html(sum)
                })
            }
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
        this.secureResetIsloading()
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
        <table ref="dataTable" class="table prevent-auto-init table-condensed display">
            <tfoot>
                <tr></tr>
            </tfoot>
        </table>
    </div>`
}