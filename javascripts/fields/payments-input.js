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
          defaultPayment: {
            id: '',
            origin: '',
            day: '',
            month: '',
            year: '',
            visibility: true
          },
          payments: [],
        };
    },
    computed: {
      value(){
        return this.payments.length === 0 
          ? '' 
          : JSON.stringify(
              Object.fromEntries(
                this.payments.filter((payment)=>{
                  return payment.id.length > 0 &&
                    payment.origin.length > 0 &&
                    this.generateDate(payment).length > 0
                }).map((payment)=>{
                  let data = {...payment}
                  delete data.id
                  delete data.visibility
                  delete data.day
                  delete data.month
                  delete data.year
                  data.date = this.generateDate(payment)
                  return  [
                    payment.id,
                    data
                  ]
                })
              )
            )
      }
    },
    methods:{
      createPayment(){
        this.payments.push({...this.defaultPayment})
      },
      convertPaymentsToArray(payments){
        return Object.entries(payments).map(this.generatePaymentFromArray)
      },
      generateDate(payment){
        if (String(payment.year).length !== 4 ||
          String(payment.month).length !== 2 ||
          String(payment.day).length !== 2){
          return '';
        }
        const date = `${payment.year}${payment.month}${payment.day}`
        const parsed = Date.parse(date)
        return isNaN(parsed) ? '' : date;
      },
      generatePaymentFromArray([id,value]){
        let data = {...this.defaultPayment,...value}
          data.id = id
          data.visibility = true
          if ('date' in data && data.date.length > 0){
            const parsed = Date.parse(data.date)
            if (!isNaN(parsed)){
              let day = parsed.getDate()
              data.day = (day < 10) ? `0${day}` : String(day)
              let month = parsed.getMonth() + 1
              data.month = (month < 10) ? `0${month}` : String(month)
              data.year = String(parsed.getYear())
            }
          }
          return data
      },
      removePayment(keyToRemove){
        this.payments.splice(keyToRemove,1)
      },
      sortArrayDateThenIdDesc(array){
        return array.sort((a,b)=>{
          const result = (a.date == b.date) ? 0 : b.date > a.date
          return (result === 0) ? (b.id >= a.id): result
        })
      },
    },
    mounted(){
      const el = $(isVueJS3 ? this.$el.parentNode : this.$el)
      let importedPayments = {}
      try {
        importedPayments = JSON.parse(el[0].dataset.payments)
      } catch (error) {
      }
      this.payments = this.sortArrayDateThenIdDesc(this.convertPaymentsToArray(importedPayments))
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