import Vue from 'vue'
import moment from 'moment'

Vue.filter('humanDiff', (value,) => {
  if (value) {
    return moment(value).fromNow();
  }

  return ''
})
