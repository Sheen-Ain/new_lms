a=5
b=10

print((a>b)and(a<b))
# true and false=false

print((a>3)and(a<b))
# ture and true=true

print((a>7)and(a<b))
# flase and true=false
print((a>8)and(a>b))
# flase and false=false


print("Or logical operators")
c=20
d=40

print((c<d)or(c>d))
# ture or false=true
print(c<d)or(d>c)
# true or true = true
print((c>d)or(c<d))
# false or true =true

print((c>d)or(c>d))
# false or false =false

print("Not logical operator")
f=50
g=100
ansf=f>g  #false
anst=f<g #true
print(ansf)
print(not ansf)
print("Answer ture me hn: ",anst)
print("logical not ney is ko false kar dia: ",not anst)

print("Membership operators")
name="Ashfaque"
print("A"in name)
fruits=['apple','banana','orange']
print('mango' in fruits)

student=['komal','marvi','saba','farooq','sammiullah','hub ali']
searc=input("Enter your name to search: ")
if searc in student:
    print("Welcome ",searc)
else:
    print("Sorry you are not registered",searc)
