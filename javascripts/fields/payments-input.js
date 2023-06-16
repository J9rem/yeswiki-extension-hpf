/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// import SpinnerLoader from './SpinnerLoader.js'

let rootsElements = ['.payment-input-field'];
let isVueJS3 = (typeof Vue.createApp == "function");

let appParams = {
    // components: {SpinnerLoader},
    data: function() {
        return {
          payments: {},
          paymentsOrder: [],
          paymentsVisibility: {}
        };
    },
    computed: {
      value(){
        return Object.keys(this.payments).length === 0 
          ? '' 
          : JSON.stringify(
              Object.fromEntries(
                Object
                  .entries(this.payments)
                  .filter(
                    ([id,data])=>{
                      return id.length > 0 && 
                        'origin' in data && 
                        data.origin.length > 0
                    }
                  )
              )
            )
      }
    },
    methods:{
      createPayment(){
        if (!('' in this.payments)){
          this.$set(this.payments,'',{date:'',origin:''})
          this.paymentsOrder.push('')
        }
      },
      day(id){
        const date = this.payments[id].date
        return String(this.extractDate(date).day).trim()
      },
      extractDate(date){
        let foundDate = {
          day:'',
          month:'',
          year:''
        }
        const parsed = Date.parse(date)
        if (isNaN(parsed)){
          if (date.length > 7){
            let dayAsNumber = Number(date.slice(6,8))
            if (!isNaN(dayAsNumber) && dayAsNumber > 0 && dayAsNumber < 32){
              foundDate.day = date.slice(6,8)
            }
            let monthAsNumber = Number(date.slice(4,6))
            if (!isNaN(monthAsNumber) && monthAsNumber > 0 && monthAsNumber < 13){
              foundDate.month = date.slice(4,6)
            }
            let yearAsNumber = Number(date.slice(0,4))
            if (!isNaN(yearAsNumber)){
              foundDate.year = date.slice(0,4)
            }
          }
        } else {
          foundDate.day = parsed.getDate()
          foundDate.month = parsed.getMonth() + 1
          foundDate.year = parsed.getFullYear()
        }
        if (foundDate.day.length > 0){
          let dayAsNumber = Number(foundDate.day)
          foundDate.day = (dayAsNumber < 10) ? `0${dayAsNumber}` : dayAsNumber
        }
        if (foundDate.month.length > 0){
          let monthAsNumber = Number(foundDate.month)
          foundDate.month = (monthAsNumber < 10) ? `0${monthAsNumber}` : monthAsNumber
        }
        return foundDate
      },
      formatDate(date){
        let formattedDate = `${date.year}${date.month}${date.day}`
        let parsed = Date.parse(formattedDate)
        if (isNaN(parsed)){
          return '' +
            (String(date.year).length !== 4 ? '    ' : String(date.year)) +
            (String(date.month).length !== 2 ? '  ' : String(date.month)) +
            (String(date.day).length !== 2 ? '  ' : String(date.day))
        } else {
          return formattedDate
        }
      },
      month(id){
        const date = this.payments[id].date
        return String(this.extractDate(date).month).trim()
      },
      removePayment(paymentId){
        if (paymentId in this.paymentsVisibility) {
          this.$delete(this.paymentsVisibility,paymentId)
        }
        if (paymentId in this.payments) {
          this.$delete(this.payments,paymentId)
        }
        if (this.paymentsOrder.includes(paymentId)){
          this.paymentsOrder = this.paymentsOrder.filter((id)=>id!=paymentId)
        }
      },
      setDatePartial(id,value,type,test){
        const asNumber = Number(value)
        if (id in this.payments && (value.length === 0 || (!isNaN(asNumber) && test(asNumber)))){
          let foundDate = this.extractDate(this.payments[id].date)
          foundDate[type] = value
          const previous = this.payments[id].date
          const newVal = this.formatDate(foundDate)
          this.payments[id].date = newVal
          console.log({id,value,type,previous,foundDate,newVal})
        }
      },
      setDay(id,day){
        this.setDatePartial(id,day,'day',(dayAsNumber)=>dayAsNumber>0 && dayAsNumber < 32)
      },
      setMonth(id,month){
        this.setDatePartial(id,month,'month',(monthAsNumber)=>monthAsNumber>0 && monthAsNumber < 13)
      },
      setYear(id,year){
        this.setDatePartial(id,year,'year',()=>true)
      },
      sortArrayDateThenIdDesc(array){
        return array.sort((a,b)=>{
          const result = (a.date == b.date) ? 0 : b.date > a.date
          return (result === 0) ? (b.id >= a.id): result
        })
      },
      toggleVisibility(paymentId){
        this.$set(this.paymentsVisibility,paymentId,(paymentId in this.paymentsVisibility) ? !this.paymentsVisibility[paymentId] : false)
      },
      updatePaymentId(oldId,newId){
        if (oldId in this.payments){
          const oldData = {...this.payments[oldId]}
          const oldVisibility = oldId in this.paymentsVisibility ? this.paymentsVisibility[oldId] : null
          this.$delete(this.payments,oldId)
          this.$delete(this.paymentsVisibility,oldId)
          this.$set(this.payments,newId,oldData)
          if (oldVisibility !== null){
            this.$set(this.paymentsVisibility,newId,oldVisibility)
          }
          if (this.paymentsOrder.includes(oldId)){
            this.paymentsOrder = this.paymentsOrder.map((id)=>(id == oldId) ? newId : id).filter((id)=>(id != oldId))
          }
        }
      },
      year(id){
        const date = this.payments[id].date
        return String(this.extractDate(date).year).trim()
      }
    },
    mounted(){
      const el = $(isVueJS3 ? this.$el.parentNode : this.$el)
      let importedPayments = {}
      try {
        importedPayments = JSON.parse(el[0].dataset.payments)
      } catch (error) {
      }
      this.payments = importedPayments
      this.paymentsOrder = this.sortArrayDateThenIdDesc(Object.entries(importedPayments).map(([id,data])=>{return{...data,...{id}}})).map((data)=>data.id)
    },
    watch: {
    }
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