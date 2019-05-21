<template>
  <div class="goods">
    <h1>{{ msg }}</h1>

    <router-link :to="{path:'/cart'}" tag="a">
      加入进货单
    </router-link>
  </div>
</template>

<script>
export default {
  name: 'goods',
  data: function () {
    return {
      msg: 'Welcome to goods Page.'
    }
  }
}
</script>
