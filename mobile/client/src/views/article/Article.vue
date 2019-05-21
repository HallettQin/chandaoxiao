<template>
  <div class="article">
    <h1>{{ msg }}</h1>
  </div>
</template>

<script>
  export default {
    name: 'article',
    data: function () {
      return {
        msg: 'Welcome to Article App'
      }
    }
  }
</script>
