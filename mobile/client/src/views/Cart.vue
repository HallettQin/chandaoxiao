<template>
  <div class="cart">
    <h1>{{ msg }}</h1>

    <router-link :to="{path:'/checkout'}" tag="a">
      订单确认
    </router-link>
  </div>
</template>

<script>
export default {
  name: 'cart',
  data: function () {
    return {
      msg: 'Welcome to Cart Page.'
    }
  }
}
</script>
